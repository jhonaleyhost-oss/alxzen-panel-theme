<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

class AnnouncementController extends Controller
{
    /**
     * @var \Prologue\Alerts\AlertsMessageBag
     */
    protected $alert;

    /**
     * AnnouncementController constructor.
     */
    public function __construct(AlertsMessageBag $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Display the index page for announcements.
     */
    public function index(): View
    {
        $query = Announcement::with('author');

        if (Schema::hasColumn('announcements', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $announcements = $query->paginate(15);

        return view('admin.announcements.index', ['announcements' => $announcements]);
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        return view('admin.announcements.new');
    }

    /**
     * Store a new announcement.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validate($request, Announcement::$validationRules);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->has('is_active');
        $data['target_display'] = $request->input('target_display', ['dashboard']);
        $data['expires_at'] = $request->input('expires_at') ?: null;

        Announcement::create($data);

        $this->alert->success('Successfully created a new announcement.')->flash();

        return redirect()->route('admin.announcements');
    }

    /**
     * Delete an announcement.
     */
    public function destroy(int $id): RedirectResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        $this->alert->success('Successfully deleted the announcement.')->flash();

        return redirect()->route('admin.announcements');
    }
}
