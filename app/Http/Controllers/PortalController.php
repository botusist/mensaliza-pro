<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        abort_unless($user->role === 'customer' && $user->customer_id, 403);
        $customer = $user->customer()->with(['charges.payments', 'contracts.plan'])->firstOrFail();

        return view('portal.dashboard', compact('customer', 'user'));
    }
}
