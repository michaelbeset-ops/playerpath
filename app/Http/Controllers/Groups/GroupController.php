<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\GroupRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Group::class);

        $groups = Group::query()
            ->withCount('players')
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'age_category' => $group->age_category,
                'is_active' => $group->is_active,
                'players_count' => $group->players_count,
            ]);

        return Inertia::render('groups/Index', [
            'groups' => $groups,
            'canManage' => $request->user()->can('create', Group::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Group::class);

        return Inertia::render('groups/Form', ['group' => null]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        Group::create($request->validated());

        return redirect()
            ->route('groups.index')
            ->with('status', 'De groep is aangemaakt.');
    }

    public function edit(Group $group): Response
    {
        $this->authorize('update', $group);

        return Inertia::render('groups/Form', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'age_category' => $group->age_category,
                'is_active' => $group->is_active,
            ],
        ]);
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return redirect()
            ->route('groups.index')
            ->with('status', 'De groep is opgeslagen.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        // De spelers zelf blijven bestaan; alleen hun indeling in deze groep
        // verdwijnt. Een groep opheffen mag nooit een speler wissen.
        $group->players()->detach();
        $group->delete();

        return redirect()
            ->route('groups.index')
            ->with('status', 'De groep is verwijderd. De spelers zijn bewaard gebleven.');
    }
}
