<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $partners = Partner::withCount(['patients', 'cases'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20);

        return view('admin.partners.index', compact('partners'));
    }

    public function create()
    {
        return view('admin.partners.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:partners',
            'phone'       => 'nullable|string',
            'website'     => 'nullable|url',
            'description' => 'nullable|string',
        ]);

        $data['slug'] = Str::slug($data['name']);

        $partner = Partner::create($data);

        // Create Passport client for this partner
        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->createClientCredentialsGrantClient(
            null,
            $partner->name . ' API Client',
            'http://localhost'
        );

        $partner->update([
            'oauth_client_id' => $client->id,
            'client_id'       => $client->id,
            'client_secret'   => $client->plainSecret ?? $client->secret,
        ]);

        return redirect()->route('admin.partners.index')->with('success', "Partner created. Client ID: {$client->id}");
    }

    public function show(int $id)
    {
        $partner = Partner::withCount(['patients', 'cases', 'offerings'])->findOrFail($id);
        // Make client_secret visible for admin credential display
        $partner->makeVisible('client_secret');
        return view('admin.partners.show', compact('partner'));
    }

    public function edit(int $id)
    {
        $partner = Partner::findOrFail($id);
        return view('admin.partners.edit', compact('partner'));
    }

    public function update(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'phone'       => 'nullable|string',
            'website'     => 'nullable|url',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:active,suspended,inactive',
        ]);

        $partner->update($data);

        return redirect()->route('admin.partners.index')->with('success', 'Partner updated.');
    }

    public function createUser(int $id)
    {
        $partner = Partner::findOrFail($id);
        return view('admin.partners.create-user', compact('partner'));
    }

    public function storeUser(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'partner_id' => $partner->id,
        ]);

        $user->assignRole('partner');

        return redirect()->route('admin.partners.show', $partner->id)
            ->with('success', "Partner user {$user->email} created. They can now log in at /login.");
    }

    public function regenerateCredentials(int $id)
    {
        $partner = Partner::findOrFail($id);

        // Revoke old Passport client if exists
        if ($partner->oauth_client_id) {
            \Laravel\Passport\Client::find($partner->oauth_client_id)?->delete();
        }

        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->createClientCredentialsGrantClient(
            null,
            $partner->name . ' API Client',
            'http://localhost'
        );

        $partner->update([
            'oauth_client_id' => $client->id,
            'client_id'       => $client->id,
            'client_secret'   => $client->plainSecret ?? $client->secret,
        ]);

        return redirect()->route('admin.partners.show', $partner->id)
            ->with('success', 'API credentials regenerated. Share the new secret with the partner immediately — it cannot be retrieved again.');
    }
}
