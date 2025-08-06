<?php

namespace App\Providers;

use Laravel\Socialite\Two\GoogleProvider;

class GmailSocialiteProvider extends GoogleProvider
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'openid',
        'profile',
        'email',
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://mail.google.com/'
    ];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://accounts.google.com/o/oauth2/v2/auth', $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return 'https://oauth2.googleapis.com/token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     * @return array
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}