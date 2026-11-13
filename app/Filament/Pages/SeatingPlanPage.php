<?php

namespace App\Filament\Pages;

use App\Models\FormSettings;
use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\PrecedenceRank;
use App\Models\SeatingPlan;
use App\Services\Seating\SeatingLayout;
use App\Services\Seating\SeatingPlanner;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class SeatingPlanPage extends Page
{
    protected string $view = 'filament.pages.seating-plan';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'seating-plan';

    #[Url]
    public ?int $meeting = null;

    public array $plan = [];

    public function mount(): void
    {
        if ($this->meeting && ! Meeting::whereKey($this->meeting)->exists()) {
            abort(404);
        }

        $saved = SeatingPlan::where('context', SeatingPlan::contextFor($this->meeting))->first();
        $planner = new SeatingPlanner($saved?->data ?? []);
        $planner->syncInvitations($this->invitations());

        if ($planner->state['layout']) {
            $planner->arrangeKeepingSeats();
        }

        $this->commit($planner);
    }

    public function getTitle(): string
    {
        return 'Seating plan: '.$this->eventTitle();
    }

    public function getSubheading(): ?string
    {
        return 'Choose a layout and the number of seats, pick the chair and the tuan rumah, and everyone else is placed by kekananan. '
            .'Drag a person onto another seat to swap them, or drag names in the running order to change rank.';
    }

    public function eventTitle(): string
    {
        return $this->meeting ? (string) Meeting::find($this->meeting)?->title : FormSettings::current()->page_heading;
    }

    private function invitations(): Collection
    {
        return Invitation::query()
            ->with('precedenceRank')
            ->when($this->meeting, fn ($q) => $q->where('meeting_id', $this->meeting), fn ($q) => $q->whereNull('meeting_id'))
            ->get();
    }

    private function planner(): SeatingPlanner
    {
        return new SeatingPlanner($this->plan);
    }

    private function commit(SeatingPlanner $planner): void
    {
        $this->plan = $planner->state;

        SeatingPlan::updateOrCreate(
            ['context' => SeatingPlan::contextFor($this->meeting)],
            ['meeting_id' => $this->meeting, 'data' => $this->plan],
        );
    }

    public function chooseLayout(string $layout): void
    {
        if (! array_key_exists($layout, SeatingLayout::LABELS)) {
            return;
        }

        $planner = $this->planner();
        $planner->state['layout'] = $layout;
        $planner->state['config'] = SeatingLayout::fitConfig($layout, count($planner->sequence()), $planner->state['config']);
        $planner->arrange();
        $this->commit($planner);
    }

    public function updatedPlan($value, $key): void
    {
        $planner = $this->planner();
        $planner->state['config'] = SeatingLayout::clamp($planner->state['config']);

        foreach (['chair', 'host'] as $role) {
            if ($planner->state[$role] === '') {
                $planner->state[$role] = null;
            }
        }

        if ($planner->state['chair'] && $planner->state['chair'] === $planner->state['host']) {
            $planner->state[$key === 'chair' ? 'host' : 'chair'] = null;
        }

        $planner->arrange();
        $this->commit($planner);
    }

    public function fitSeats(): void
    {
        $planner = $this->planner();
        $planner->state['config'] = SeatingLayout::fitConfig($planner->state['layout'], count($planner->sequence()), $planner->state['config']);
        $planner->arrange();
        $this->commit($planner);
    }

    public function refill(): void
    {
        $planner = $this->planner();
        $planner->arrange();
        $this->commit($planner);
    }

    public function resortByRank(): void
    {
        $planner = $this->planner();
        $planner->resortByRank();
        $planner->arrange();
        $this->commit($planner);
    }

    public function refreshGuests(): void
    {
        $planner = $this->planner();
        $planner->syncInvitations($this->invitations());
        $planner->arrange();
        $this->commit($planner);

        Notification::make()->title('Guest list refreshed')->success()->send();
    }

    public function startOver(): void
    {
        $planner = $this->planner();
        $planner->state['layout'] = null;
        $planner->state['seats'] = [];
        $planner->state['chair'] = null;
        $planner->state['host'] = null;
        $this->commit($planner);
    }

    public function dropOnSeat(int $seq, ?string $kind, ?string $value): void
    {
        $planner = $this->planner();

        if ($kind === 'seat' && $value !== null) {
            $planner->swap((int) $value, $seq);
        } elseif ($kind === 'person' && $value !== null) {
            $planner->place($value, $seq);
        }

        $this->commit($planner);
    }

    public function dropOnTray(?string $kind, ?string $value): void
    {
        if ($kind !== 'seat' || $value === null) {
            return;
        }

        $planner = $this->planner();
        $planner->unseat((int) $value);
        $this->commit($planner);
    }

    public function reorder(?string $kind, ?string $key, ?string $beforeKey): void
    {
        $planner = $this->planner();

        if ($kind === 'seat') {
            $key = $planner->state['seats'][(string) $key] ?? null;
        }

        if ($key === null || $key === $beforeKey || ! isset($planner->state['people'][$key])) {
            return;
        }

        $planner->moveInOrder($key, $beforeKey);
        $this->commit($planner);
    }

    public function setPresent(string $key, bool $present): void
    {
        $planner = $this->planner();
        $planner->setPresent($key, $present);
        $this->commit($planner);
    }

    public function removeManual(string $key): void
    {
        if (! str_starts_with($key, 'm:')) {
            return;
        }

        $planner = $this->planner();
        $planner->removePerson($key);
        $planner->arrange();
        $this->commit($planner);
    }

    private function personFields(): array
    {
        return [
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('org')->label('Ministry / organisation')->maxLength(255),
            TextInput::make('title')->label('Position (jawatan)')->maxLength(255)->live(onBlur: true),
            Select::make('rank_id')
                ->label('Kekananan: same level as')
                ->helperText('Search by position, name or short form (KSU, SUK, JUSA A…). Leave empty to put them last.')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => PrecedenceRank::options($search))
                ->getOptionLabelUsing(fn ($value) => PrecedenceRank::find($value)?->label())
                ->live(),
            Toggle::make('remember')
                ->label('Also add this position to the Kekananan list, right after the chosen entry')
                ->visible(fn (Get $get) => filled($get('title')) && filled($get('rank_id'))),
        ];
    }

    private function personFromForm(array $data): array
    {
        $rank = PrecedenceRank::find($data['rank_id'] ?? null);

        if ($rank && ($data['remember'] ?? false) && filled($data['title'] ?? null)) {
            $rank = PrecedenceRank::create([
                'title' => $data['title'],
                'holder' => $data['name'],
                'sort' => $rank->sort + 5,
            ]);
            PrecedenceRank::renumber();
            $rank->refresh();
        }

        return SeatingPlanner::fromRank($rank, [
            'key' => 'm:'.Str::random(8),
            'name' => $data['name'],
            'org' => $data['org'] ?? null,
            'title' => $data['title'] ?? null,
        ]);
    }

    public function addPersonAction(): Action
    {
        return Action::make('addPerson')
            ->label('Add person')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments) => match ($arguments['role'] ?? null) {
                'chair' => 'Add the chair (not on the guest list)',
                'host' => 'Add the tuan rumah (not on the guest list)',
                default => 'Add someone who is not on the guest list',
            })
            ->schema($this->personFields())
            ->action(function (array $data, array $arguments) {
                $planner = $this->planner();
                $key = $planner->addPerson($this->personFromForm($data));

                if (in_array($arguments['role'] ?? null, ['chair', 'host'], true)) {
                    $planner->state[$arguments['role']] = $key;
                }

                if ($planner->state['layout']) {
                    $planner->arrange();
                }

                $this->commit($planner);
            });
    }

    public function replaceAction(): Action
    {
        return Action::make('replace')
            ->iconButton()
            ->icon('heroicon-m-arrows-right-left')
            ->size('xs')
            ->color('gray')
            ->tooltip('Replace with someone else')
            ->modalHeading(fn (array $arguments) => 'Replace the person in seat '.($arguments['seq'] ?? ''))
            ->modalDescription('The person now in this seat is marked as not present. The replacement takes this seat and this place in the running order.')
            ->schema([
                Toggle::make('is_new')->label('Someone not on the list')->live(),
                Select::make('existing')
                    ->label('Replacement')
                    ->options(fn () => collect($this->plan['order'])
                        ->reject(fn ($k) => in_array($k, $this->plan['seats'], true))
                        ->mapWithKeys(fn ($k) => [$k => $this->plan['people'][$k]['name'].($this->plan['people'][$k]['title'] ? ' — '.$this->plan['people'][$k]['title'] : '')])
                        ->all())
                    ->searchable()
                    ->required(fn (Get $get) => ! $get('is_new'))
                    ->visible(fn (Get $get) => ! $get('is_new')),
                ...array_map(
                    fn ($field) => $field->visible(fn (Get $get) => (bool) $get('is_new'))->required(fn (Get $get) => $field->getName() === 'name' && $get('is_new')),
                    $this->personFields(),
                ),
            ])
            ->action(function (array $data, array $arguments) {
                $planner = $this->planner();
                $key = ($data['is_new'] ?? false)
                    ? $planner->addPerson($this->personFromForm($data))
                    : $data['existing'];

                $planner->replace((int) $arguments['seq'], $key);
                $this->commit($planner);
            });
    }

    public function downloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->size('sm')
            ->disabled(fn () => ! $this->plan['layout'])
            ->action(function () {
                $data = $this->viewModel();
                $pdf = Pdf::loadView('exports.seating-plan', $data + ['title' => $this->eventTitle()])
                    ->setPaper('a4', 'landscape');

                return response()->streamDownload(
                    fn () => print ($pdf->output()),
                    'Seating plan - '.Str::of($this->eventTitle())->replaceMatches('/[^\pL\pN\s\-]+/u', '')->squish()->limit(60, '').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public function viewModel(): array
    {
        $planner = $this->planner();
        $state = $planner->state;
        $layout = $state['layout'] ? SeatingLayout::build($state['layout'], $state['config']) : null;

        return [
            'state' => $state,
            'layout' => $layout,
            'misplaced' => $planner->misplaced(),
            'unseated' => $planner->unseated(),
            'absent' => $planner->absent(),
            'seatOf' => array_flip($state['seats']),
            'seatCount' => $planner->seatCount(),
            'needed' => count($planner->sequence()),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewModel();
    }
}
