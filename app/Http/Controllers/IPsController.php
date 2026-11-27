<?php

namespace App\Http\Controllers;

use App\Models\IPs;
use App\Models\Reseller;
use App\Models\SeedBoxes;
use App\Models\Server;
use App\Models\Shared;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class IPsController extends Controller
{
    public function index()
    {
        $ips = IPs::all();
        return view('ips.index', compact(['ips']));
    }

    public function create()
    {
        $servers = Server::all();
        $shareds = Shared::all();
        $resellers = Reseller::all();
        $seed_boxes = SeedBoxes::all();
        return view('ips.create', compact(['servers', 'shareds', 'resellers', 'seed_boxes']));
    }

    public function store(Request $request)
    {
        $request->validate([
            'address' => 'required|ip|min:2',
            'service_id' => 'required|string'
        ]);

        $ip = IPs::insertIP($request->service_id, $request->address);
        $server = Server::find($ip->service_id);
        $this->forgetAddressCaches($ip->service_id, $server !== null);
        try {
            IPs::getUpdateIpInfo($ip);
        } catch (Throwable $e) {
            report($e);
        }

        $destination = $server && $request->boolean('return_to_server')
            ? redirect()->route('servers.show', $server)
            : redirect()->route('IPs.index');

        return $destination
            ->with('success', 'IP address created Successfully.');
    }

    public function destroy(IPs $IP)
    {
        if ($IP->delete()) {
            $this->forgetAddressCaches($IP->service_id, Server::whereKey($IP->service_id)->exists());
            return redirect()->route('IPs.index')
                ->with('success', 'IP address was deleted Successfully.');
        }
        return redirect()->route('IPs.index')
            ->with('error', 'IP was not deleted.');
    }

    private function forgetAddressCaches(string $serviceId, bool $isServer): void
    {
        Cache::forget("ip_addresses.$serviceId");
        Cache::forget('map_data');
        if ($isServer) {
            Server::serverSpecificCacheForget($serviceId);
            Server::serverRelatedCacheForget();
        }
    }

    public function getUpdateWhoIs(IPs $IP): \Illuminate\Http\RedirectResponse
    {
        $result = IPs::getUpdateIpInfo($IP);

        if ($result) {
            return redirect()->route('IPs.index')
                ->with('success', 'IP address updated Successfully.');
        }
        return redirect()->route('IPs.index')
            ->with('error', 'IP information could not be retrieved. The lookup service may be unavailable or rate-limited.');
    }

}
