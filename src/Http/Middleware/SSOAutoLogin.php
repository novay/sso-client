<?php

namespace Novay\SSO\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Novay\SSO\Services\Broker;

class SSOAutoLogin
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $broker = new Broker();
        $response = $broker->getUserInfo();

        // If client is logged out in SSO server but still logged in broker.
        if (!isset($response['data']) && !auth()->guest()) {
            return $this->logout($request);
        }

        // If there is a problem with data in SSO server, we will re-attach client session.
        if (isset($response['error']) && strpos($response['error'], 'There is no saved session data associated with the broker session id') !== false) {
            return $this->clearSSOCookie($request);
        }

        // If client is logged in SSO server and didn't logged in broker...
        if (isset($response['data']) && (auth()->guest() || auth()->user()->id != $response['data']['id'])) {
            // ... we will authenticate our client.
            $this->handleLogin($response);
        }

        return $next($request);
    }

    /**
     * Manage your users models as your default credentials
     *
     * @param Broker $response
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleLogin($response)
    {
        $user = config('sso.model')::updateOrCreate([
            'id'  => $response['data']['id'], 
        ], [
            'name' => $response['data']['name'],
            'email' => $response['data']['email'], 
            'password' => 'secret'
        ]);

        // \JWTAuth::fromUser($user);
        auth()->loginUsingId($user->id);
    }

    /**
     * Clearing SSO cookie so broker will re-attach SSO server session.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function clearSSOCookie(Request $request)
    {
        return redirect($request->fullUrl())->cookie(cookie('sso_token_' . config('sso.broker_name')));
    }

    /**
     * Logging out authenticated user.
     * Need to make a page refresh because current page may be accessible only for authenticated users.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function logout(Request $request)
    {
        auth()->logout();
        return redirect($request->fullUrl());
    }
}