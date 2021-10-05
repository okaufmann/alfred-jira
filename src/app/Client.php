<?php

namespace Okaufmann\AlfredJiraSearch;

use Spatie\Regex\Regex;
use Zttp\Zttp;

class Client
{
    protected $authCache;
    protected $versionCache;
    protected $authContext;
    protected $version;
    protected $alfredVersion;

    public function __construct()
    {
        $this->cacheDir = getenv('alfred_workflow_cache');
        $this->authCache = getenv('alfred_workflow_cache').'/auth.txt';
        $this->version = getenv('alfred_workflow_version');
        $this->alfredVersion = getenv('alfred_version');

        if (! file_exists($this->cacheDir)) {
            mkdir($this->cacheDir);
        }

        if (! file_exists($this->authCache)) {
            file_put_contents($this->authCache, '');
        }

        $this->authContext = json_decode(file_get_contents($this->authCache), true);
    }

    public function setAuthContext($input)
    {
        [$spaceId, $email, $token] = explode(',', $input);

        $this->authContext = [
            'spaceId' => $spaceId,
            'email' => $email,
            'token' => $token
        ];

        file_put_contents($this->authCache, json_encode($this->authContext));
        $response = $this->makeRequest('/rest/api/3/myself', 'get');

        if ($response['accountId']) {
            $this->respond('Logged in yey!');
        } else {
            $this->respond('There was a problem!');
        }
    }

    public function emitError($text)
    {
        $command = "echo $(osascript -e 'tell application id \"com.runningwithcrayons.Alfred\" to run trigger \"push\" in workflow \"com.alfredapp.okaufmann.jira-search\" with argument \"{$text}\"')";
        $response = exec($command);
    }

    protected function respond($arg = '', $variables = [])
    {
        $defaultVars = [
            'push_title' => 'jira',
        ];

        $alfredObj = [
            'alfredworkflow' => [
                'arg' => $arg,
                'variables' => array_merge($defaultVars, $variables),
            ],
        ];

        echo json_encode($alfredObj);
    }

    protected function makeRequest($url, $method, $data = null, $headers = null)
    {
        $response = Zttp::withBasicAuth($this->authContext['email'], $this->authContext['token'])
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => "Jira Workflow v{$this->version} hosted by Alfred(v{$this->alfredVersion})",
            ])
            ->$method("{$this->buildApiBaseUrl()}$url", $data);

        if ($this->requestHasError($response->getStatusCode())) {
            die();
        }

        if (str_contains($response->header('Content-Type'), 'application/json')) {
            return $response->json();
        }

        return (string) $response->body();
    }

    protected function requestHasError($code)
    {
        if ($code >= 400 && $code !== 400) {
            $errorMap = [
                // 400 => '(400) Valid data was given but the request has failed.',
                401 => '(401) No valid API Key was given.',
                404 => '(404) The request resource could not be found.',
                422 => '(422) The payload has missing required parameters or invalid data was given.',
                429 => '(429) Too many attempts.',
                500 => '(500) Request failed due to an internal error in Forge.',
                503 => '(503) Offline for maintenance.',
            ];

            if (isset($errorMap[$code])) {
                $this->emitError($errorMap[$code]);
            } else {
                $this->emitError('An unknown error occured, maybe try re-setting your API Key in Alfred');
            }

            return true;
        }

        return false;
    }

    public function buildQuery($search)
    {
        $match = Regex::match('/^(\w+-\d+).*$/', $search);
        if($match->hasMatch()) {
            return "issueKey = '{$match->group(1)}'";
        }

        return "summary ~ '{$search}'";
    }

    public function buildApiBaseUrl()
    {
        return "https://{$this->authContext['spaceId']}.atlassian.net";
    }
}
