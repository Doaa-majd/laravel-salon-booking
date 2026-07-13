<?php

namespace App\Livewire\Admin;

use App\Actions\Clients\SaveClient;
use App\Concerns\ClientValidationRules;
use App\Models\Appointment;
use App\Models\Client;
use App\Rules\PhoneNumber;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Clients')]
class Clients extends Component
{
    use ClientValidationRules;

    public ?int $editingId = null;

    public bool $showForm = false;

    #[Url]
    public string $search = '';

    public string $name = '';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $birthdate = null;

    public ?string $skin_type = null;

    public ?string $hair_type = null;

    public ?string $notes = null;

    /** Comma-separated tags, edited as free text. */
    public string $tagsInput = '';

    /**
     * Open the create form.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Open the edit form for an existing client.
     */
    public function edit(Client $client): void
    {
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->phone = $client->phone;
        $this->email = $client->email;
        $this->birthdate = $client->birthdate?->format('Y-m-d');
        $this->skin_type = $client->skin_type;
        $this->hair_type = $client->hair_type;
        $this->notes = $client->notes;
        $this->tagsInput = implode(', ', $client->tags ?? []);
        $this->showForm = true;
    }

    /**
     * Validate and persist the client.
     */
    public function save(SaveClient $action): void
    {
        $this->phone = PhoneNumber::normalize((string) $this->phone);

        $data = $this->validate($this->clientRules());

        $data['tags'] = collect(explode(',', $this->tagsInput))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $client = $this->editingId
            ? Client::query()->findOrFail($this->editingId)
            : null;

        $action->handle($client, $data);

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Client saved.'));
    }

    /**
     * Soft-delete a client (history is preserved).
     */
    public function delete(Client $client): void
    {
        $client->delete();

        Flux::toast(variant: 'success', text: __('Client deleted.'));
    }

    /**
     * Reset the form to its default state.
     */
    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->phone = null;
        $this->email = null;
        $this->birthdate = null;
        $this->skin_type = null;
        $this->hair_type = null;
        $this->notes = null;
        $this->tagsInput = '';
        $this->resetValidation();
    }

    /**
     * Clients matching the search term, with visit count and last-visit date.
     *
     * @return Collection<int, Client>
     */
    protected function clients(): Collection
    {
        return Client::query()
            ->search($this->search)
            ->withCount('appointments')
            ->addSelect(['last_visit_at' => Appointment::query()
                ->select('datetime_start')
                ->whereColumn('client_id', 'clients.id')
                ->orderByDesc('datetime_start')
                ->limit(1),
            ])
            ->orderBy('name')
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.admin.clients', [
            'clients' => $this->clients(),
        ]);
    }
}
