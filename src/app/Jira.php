<?php

namespace Okaufmann\AlfredJiraSearch;

use Alfred\Workflows\Workflow;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;

class Jira extends Client
{
    public function search($text)
    {
        $baseUrl = '/rest/api/3/search';

        $text = $this->normalizeUnicodeChars($text);
        $jwpQuery = $this->buildQuery($text);
        $params = [
            'jql' => $jwpQuery,
        ];

        $requestUrl = "{$baseUrl}?" . http_build_query($params);
        $json = $this->makeRequest($requestUrl, 'get');

        return $this->searchResponse($json);
    }

    /**
     * Normalizes decomposed unicode chars.
     *
     * @param $text
     * @return string
     */
    protected function normalizeUnicodeChars($text)
    {
        return Normalizer::normalize($text);
    }

    protected function buildPreviewUrl($issueId)
    {
        $previewUrl = "{$this->buildApiBaseUrl()}/browse/{$issueId}";

        return $previewUrl;
    }

    protected function searchResponse($json)
    {
        $wf = new Workflow;

        if (isset($json['issues']) && count($json['issues']) > 0) {

            foreach ($json['issues'] as $index => $issue) {
                $previewUrl = $this->buildPreviewUrl($issue['key']);

                $wf->result()
                    ->uid($index . time())
                    ->title($issue['key'])
                    ->subtitle($issue['fields']['summary'])
                    ->quicklookurl($previewUrl)
                    ->copy($previewUrl)
                    ->arg($previewUrl)
                    ->icon('icon.png');
            }

            return $wf->output();
        }

        $wf->result()
            ->uid(time())
            ->title('Nothing found...')
            ->valid(false)
            ->icon('icon.png');

        return $wf->output();
    }
}
