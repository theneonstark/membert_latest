<?php

namespace App\Http\Middleware;

use Closure;

class LocationCapture
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $iplocation = \DB::table("ip_locations")->where('ip', $request->ip())->first();

        if($iplocation){
            $request['lat'] = $iplocation->lat;
            $request['lon'] = $iplocation->lon;
            $request['ip_location']  = $iplocation->lat."/".$iplocation->lon;
        }else{

            $url      = "http://ip-api.com/json/".$request->ip();
            $result   = \Myhelper::curl($url, "GET", "", [], "no", "", "");
            $response = json_decode($result['response']);
            if (isset($response->lat)){
                $request['lat'] = $response->lat;
                $request['lon'] = $response->lon;
                $request['ip_location']  = $request->lat."/".$request->lon;

                try {
                    \DB::table("ip_locations")->insert([
                        "ip"  => $request->ip(),
                        "lat" => $request->lat,
                        "lon" => $request->lon
                    ]);
                } catch (\Exception $e) {}
            }else{
                $request['lat'] = 28.4597;
                $request['lon'] = 77.0282;
                $request['ip_location']  = $request->lat."/".$request->lon;
            }
        }

        if(!$request->has("gps_location") || $request->gps_location == ""){
            $request['gps_location'] = "0/0";
        }else{
            $gpsdata = explode("/", $request->gps_location);
            $request['lat'] = $gpsdata[0];
            $request['lon'] = $gpsdata[1];
        }
        
        return $next($request);
    }
}
