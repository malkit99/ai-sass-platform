<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactGroup;
use App\Rules\ValidMobileNumber;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactController extends Controller
{
    // Baileys' onWhatsApp accepts a batch, but a single request-response cycle
    // still has to complete within the bridge's own timeout — cap how many a
    // single "Validate" click processes rather than risk a huge unselected
    // group hanging the request.
    private const MAX_VALIDATE_BATCH = 500;

    private const PARAM_COUNT = 20;

    public function __construct(private readonly BridgeClient $bridge) {}

    public function index(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('view', $group);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return $group->contacts()
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('phone', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->latest()
            ->paginate($data['per_page'] ?? 20);
    }

    /**
     * Adds a single contact (the Import dialog's "Via Form" tab) — same
     * row logic as a one-line CSV import, so a hand-typed number gets the
     * same format check + upsert-by-phone behavior as a bulk import.
     */
    public function store(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'phone' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'params' => ['nullable', 'array', 'max:'.self::PARAM_COUNT],
            'params.*' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = preg_replace('/\D/', '', $data['phone']);
        if ($phone === '') {
            throw ValidationException::withMessages(['phone' => ['Enter a valid phone number.']]);
        }

        $params = [];
        foreach (array_values($data['params'] ?? []) as $i => $value) {
            if (trim((string) $value) !== '') {
                $params['param'.($i + 1)] = trim($value);
            }
        }

        [$contact, $created] = $this->upsertContactRow($group, $phone, $data['name'] ?? null, $params);

        return response()->json($contact, $created ? 201 : 200);
    }

    /**
     * Bulk-imports contacts from a CSV or Excel (.xlsx/.xls) file with
     * columns Phone, Name, Param1..Param20 (case-insensitive, any order) —
     * Phone/Name feed the message body, Param1-20 feed template variable
     * substitution during a bulk campaign. Existing rows (matched by phone
     * within this group) are updated rather than duplicated; a previously
     * live-validated status is left alone so a re-import can't silently
     * undo a real WhatsApp check.
     */
    public function import(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = in_array($extension, ['xlsx', 'xls'], true)
            ? IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, true, false)
            : $this->readCsvRows($file->getRealPath());

        if (! $rows) {
            throw ValidationException::withMessages(['file' => ['The file is empty.']]);
        }

        $header = array_shift($rows);
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        $columns = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $phoneCol = array_search('phone', $columns, true);
        if ($phoneCol === false) {
            $phoneCol = array_search('phone number', $columns, true);
        }
        $nameCol = array_search('name', $columns, true);

        if ($phoneCol === false) {
            throw ValidationException::withMessages(['file' => ['Missing required "Phone" column.']]);
        }

        $paramCols = [];
        for ($i = 1; $i <= self::PARAM_COUNT; $i++) {
            $index = array_search('param'.$i, $columns, true);
            if ($index !== false) {
                $paramCols["param{$i}"] = $index;
            }
        }

        $imported = 0;
        $updated = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            $phone = preg_replace('/\D/', '', (string) ($row[$phoneCol] ?? ''));
            if ($phone === '') {
                continue;
            }

            $name = $nameCol !== false ? trim((string) ($row[$nameCol] ?? '')) : null;

            $params = [];
            foreach ($paramCols as $key => $index) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $params[$key] = $value;
                }
            }

            [, $created, $wasInvalid] = $this->upsertContactRow($group, $phone, $name, $params);

            if ($created) {
                $imported++;
                if ($wasInvalid) {
                    $invalid++;
                }
            } else {
                $updated++;
            }
        }

        return response()->json([
            'imported' => $imported,
            'updated' => $updated,
            'invalid' => $invalid,
        ]);
    }

    /**
     * @return array{0: WhatsappContact, 1: bool, 2: bool} [contact, wasCreated, createdAsInvalidFormat]
     */
    private function upsertContactRow(WhatsappContactGroup $group, string $phone, ?string $name, array $params): array
    {
        $existing = $group->contacts()->where('phone', $phone)->first();

        if ($existing) {
            $existing->update([
                'name' => $name ?: $existing->name,
                'params' => $params ?: $existing->params,
            ]);

            return [$existing, false, false];
        }

        $isValidFormat = Validator::make(['phone' => $phone], ['phone' => [new ValidMobileNumber]])->passes();

        $contact = $group->contacts()->create([
            'phone' => $phone,
            'name' => $name ?: null,
            'params' => $params ?: null,
            'status' => $isValidFormat ? WhatsappContact::STATUS_UNKNOWN : WhatsappContact::STATUS_INVALID,
        ]);

        return [$contact, true, ! $isValidFormat];
    }

    private function readCsvRows(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Best-effort import of the connected number's own synced WhatsApp
     * contacts (see BridgeClient::fetchDeviceContacts) — only reflects
     * contacts streamed in since that instance last connected.
     */
    public function importFromWhatsapp(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        $contacts = $this->bridge->fetchDeviceContacts($channel->id);

        $imported = 0;
        $updated = 0;

        foreach ($contacts as $contact) {
            $phone = preg_replace('/\D/', '', $contact['phone'] ?? '');
            if ($phone === '') {
                continue;
            }

            $existing = $group->contacts()->where('phone', $phone)->first();

            if ($existing) {
                if ($contact['name'] && ! $existing->name) {
                    $existing->update(['name' => $contact['name']]);
                }
                $updated++;
            } else {
                $group->contacts()->create([
                    'phone' => $phone,
                    'name' => $contact['name'] ?? null,
                    'status' => WhatsappContact::STATUS_UNKNOWN,
                ]);
                $imported++;
            }
        }

        return response()->json(['imported' => $imported, 'updated' => $updated]);
    }

    public function export(WhatsappContactGroup $group)
    {
        $this->authorize('view', $group);

        $paramHeaders = array_map(fn ($i) => "Param{$i}", range(1, self::PARAM_COUNT));

        return response()->streamDownload(function () use ($group, $paramHeaders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Phone', 'Name', ...$paramHeaders, 'Status']);

            $group->contacts()->orderBy('id')->chunk(500, function ($contacts) use ($out) {
                foreach ($contacts as $contact) {
                    $params = $contact->params ?? [];
                    $paramValues = array_map(fn ($i) => $params["param{$i}"] ?? '', range(1, self::PARAM_COUNT));
                    fputcsv($out, [$contact->phone, $contact->name, ...$paramValues, $contact->status]);
                }
            });

            fclose($out);
        }, "{$group->name}-contacts.csv");
    }

    /**
     * Checks selected (or, if none selected, the oldest not-yet-checked)
     * contacts against WhatsApp's own registration lookup via the given
     * connected instance — see BridgeClient::checkNumbers.
     */
    public function validateNumbers(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'ids' => ['nullable', 'array', 'max:'.self::MAX_VALIDATE_BATCH],
            'ids.*' => ['integer'],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        $liveStatus = $this->bridge->status($channel->id)['status'] ?? Channel::STATUS_DISCONNECTED;
        if ($liveStatus !== Channel::STATUS_CONNECTED) {
            throw ValidationException::withMessages([
                'channel_id' => ['This WhatsApp account is disconnected. Reconnect it before validating numbers.'],
            ]);
        }

        $contacts = $group->contacts()
            ->when(
                ! empty($data['ids']),
                fn ($q) => $q->whereIn('id', $data['ids']),
                fn ($q) => $q->where('status', WhatsappContact::STATUS_UNKNOWN)->limit(self::MAX_VALIDATE_BATCH),
            )
            ->get();

        if ($contacts->isEmpty()) {
            return response()->json(['checked' => 0, 'valid' => 0, 'invalid' => 0]);
        }

        $results = $this->bridge->checkNumbers($channel->id, $contacts->pluck('phone')->all());
        $existsByPhone = collect($results)->pluck('exists', 'phone');

        $validCount = 0;
        $invalidCount = 0;

        foreach ($contacts as $contact) {
            $exists = $existsByPhone->get($contact->phone, false);
            $contact->update(['status' => $exists ? WhatsappContact::STATUS_VALID : WhatsappContact::STATUS_INVALID]);
            $exists ? $validCount++ : $invalidCount++;
        }

        return response()->json(['checked' => $contacts->count(), 'valid' => $validCount, 'invalid' => $invalidCount]);
    }

    public function deleteInvalid(WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $count = $group->contacts()->where('status', WhatsappContact::STATUS_INVALID)->delete();

        return response()->json(['deleted' => $count]);
    }

    public function bulkDestroy(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $group->contacts()->whereIn('id', $data['ids'])->delete();

        return response()->noContent();
    }

    public function destroy(WhatsappContactGroup $group, WhatsappContact $contact)
    {
        $this->authorize('update', $group);

        abort_unless($contact->contact_group_id === $group->id, 404);

        $contact->delete();

        return response()->noContent();
    }
}
