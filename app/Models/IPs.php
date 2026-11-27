<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IPs extends Model
{
    use HasFactory;

    public $table = 'ips';

    protected $keyType = 'string';

    protected $fillable = ['id', 'service_id', 'address', 'is_ipv4', 'active', 'continent', 'country', 'region', 'city', 'org', 'isp', 'asn', 'timezone_gmt', 'latitude', 'longitude', 'fetched_at'];

    public $incrementing = false;

    public static function deleteIPsAssignedTo($service_id): void
    {
        DB::table('ips')->where('service_id', $service_id)->delete();
        Cache::forget('map_data');
    }

    public static function insertIP(string $service_id, string $address): IPs
    {
        $ip = self::create(
            [
                'id' => Str::random(8),
                'service_id' => $service_id,
                'address' => $address,
                'is_ipv4' => (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) ? 0 : 1,
                'active' => 1
            ]
        );

        Cache::forget('map_data');

        return $ip;
    }

    public static function ipsForServer(string $server_id)
    {
        return Cache::remember("ip_addresses.$server_id", now()->addHours(1), function () use ($server_id) {
            return json_decode(DB::table('ips as i')
                ->where('i.service_id', $server_id)
                ->get(), true);
        });
    }

    public function note(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Note::class, 'service_id', 'id');
    }

    public static function getUpdateIpInfo(IPs $IP): bool
    {
        if (! filter_var($IP->address, FILTER_VALIDATE_IP)) {
            return false;
        }
        try {
            $response = Http::timeout(10)->get('https://ipwho.is/'.rawurlencode($IP->address));
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            return false;
        }
        $data = $response->json();
        if (! $response->ok() || ! is_array($data) || ($data['success'] ?? false) !== true
            || ! is_string($data['ip'] ?? null) || @inet_pton($data['ip']) !== inet_pton($IP->address)) {
            return false;
        }
        $values = [
            'continent' => $data['continent'] ?? null,
            'country' => $data['country'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'org' => data_get($data, 'connection.org'),
            'isp' => data_get($data, 'connection.isp'),
            'asn' => isset($data['connection']['asn']) ? 'AS'.$data['connection']['asn'] : null,
            'timezone_gmt' => data_get($data, 'timezone.utc'),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ];
        $IP->update(array_filter($values, fn ($value) => $value !== null) + ['fetched_at' => now()]);
        Cache::forget('map_data');
        Cache::forget("ip_addresses.$IP->service_id");
        Server::serverSpecificCacheForget($IP->service_id);
        Server::serverRelatedCacheForget();
        return true;
    }

}
