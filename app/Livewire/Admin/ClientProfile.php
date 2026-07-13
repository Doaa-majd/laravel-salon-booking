<?php

namespace App\Livewire\Admin;

use App\Actions\Clients\AddClientPhoto;
use App\Enums\AppointmentStatus;
use App\Enums\ClientPhotoType;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPhoto;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Client profile')]
class ClientProfile extends Component
{
    use WithFileUploads;

    public Client $client;

    public string $photoType = 'before';

    public ?int $photoAppointmentId = null;

    public ?string $photoNote = null;

    public string $photoTakenAt = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(Client $client): void
    {
        $this->client = $client;
        $this->photoTakenAt = now()->format('Y-m-d\TH:i');
    }

    /**
     * Validate and store a before/after photo for this client.
     */
    public function addPhoto(AddClientPhoto $action): void
    {
        $data = $this->validate([
            'photo' => ['required', 'image', 'max:4096'],
            'photoType' => ['required', Rule::enum(ClientPhotoType::class)],
            'photoAppointmentId' => [
                'nullable',
                Rule::exists('appointments', 'id')->where('client_id', $this->client->id),
            ],
            'photoNote' => ['nullable', 'string', 'max:255'],
            'photoTakenAt' => ['required', 'date'],
        ]);

        $action->handle($this->client, [
            'appointment_id' => $data['photoAppointmentId'] ?? null,
            'type' => $data['photoType'],
            'image_path' => $this->photo?->store('client-photos', 'public'),
            'note' => $data['photoNote'] ?? null,
            'taken_at' => $data['photoTakenAt'],
        ]);

        $this->reset('photo', 'photoNote', 'photoAppointmentId');
        $this->photoType = 'before';
        $this->photoTakenAt = now()->format('Y-m-d\TH:i');

        Flux::toast(variant: 'success', text: __('Photo added.'));
    }

    /**
     * Remove a photo belonging to this client.
     */
    public function removePhoto(int $photoId): void
    {
        $this->client->photos()->whereKey($photoId)->delete();

        Flux::toast(variant: 'success', text: __('Photo removed.'));
    }

    /**
     * The client's appointments (most recent first) with service snapshots.
     *
     * @return Collection<int, Appointment>
     */
    protected function appointments(): Collection
    {
        return $this->client->appointments()
            ->with(['staff', 'appointmentServices.service'])
            ->orderByDesc('datetime_start')
            ->get();
    }

    /**
     * The client's photos, grouped into before/after for the gallery.
     *
     * @return Collection<int, ClientPhoto>
     */
    protected function photos(): Collection
    {
        return $this->client->photos()->orderByDesc('taken_at')->get();
    }

    /**
     * Total amount spent on completed appointments (from price snapshots).
     */
    protected function totalSpend(): string
    {
        return (string) $this->client->appointments()
            ->where('status', AppointmentStatus::Completed)
            ->sum('price_snapshot');
    }

    public function render(): mixed
    {
        return view('livewire.admin.client-profile', [
            'appointments' => $this->appointments(),
            'photos' => $this->photos(),
            'totalSpend' => $this->totalSpend(),
        ]);
    }
}
