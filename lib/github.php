<?php
/**
 * GitHub REST API client.
 * All requests are authenticated with the user's OAuth token.
 */

class GitHubClient {

    private const BASE = 'https://api.github.com';

    public function __construct(private string $token) {}

    // -------------------------------------------------------------------------
    // Core HTTP
    // -------------------------------------------------------------------------

    /**
     * Execute a GitHub API request.
     * Throws RuntimeException with the HTTP status code on non-2xx responses.
     */
    private function request(string $method, string $path, array $body = []): mixed {
        $ch = curl_init(self::BASE . $path);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: GitHub-Kanban-App/1.0',
            'Content-Type: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        } elseif ($method === 'PATCH') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            $opts[CURLOPT_POSTFIELDS]    = json_encode($body);
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $opts);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("cURL error: {$err}");
        }

        $data = json_decode($raw, true);

        if ($status >= 400) {
            $msg = $data['message'] ?? "GitHub API error (HTTP {$status})";
            throw new RuntimeException($msg, $status);
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // User
    // -------------------------------------------------------------------------

    public function get_user(): array {
        return $this->request('GET', '/user');
    }

    // -------------------------------------------------------------------------
    // Repositories
    // -------------------------------------------------------------------------

    /**
     * Fetch all repositories the authenticated user can access.
     * Paginates through all pages automatically (up to 10 pages = 1000 repos).
     */
    public function get_repos(): array {
        $all  = [];
        $page = 1;
        while ($page <= 10) {
            $batch = $this->request('GET', "/user/repos?sort=updated&per_page=100&page={$page}");
            if (empty($batch)) {
                break;
            }
            $all = array_merge($all, $batch);
            if (count($batch) < 100) {
                break;
            }
            $page++;
        }
        return $all;
    }

    // -------------------------------------------------------------------------
    // Milestones
    // -------------------------------------------------------------------------

    public function get_milestones(string $owner, string $repo, string $state = 'open'): array {
        return $this->request('GET', "/repos/{$owner}/{$repo}/milestones?state={$state}&per_page=100");
    }

    // -------------------------------------------------------------------------
    // Issues
    // -------------------------------------------------------------------------

    public function get_issues(string $owner, string $repo, int $milestone): array {
        $all  = [];
        $page = 1;
        while ($page <= 10) {
            $batch = $this->request(
                'GET',
                "/repos/{$owner}/{$repo}/issues?milestone={$milestone}&state=all&per_page=100&page={$page}"
            );
            if (empty($batch)) {
                break;
            }
            // Filter out pull requests (GitHub includes them in issues endpoint)
            $issues = array_filter($batch, fn($i) => !isset($i['pull_request']));
            $all    = array_merge($all, array_values($issues));
            if (count($batch) < 100) {
                break;
            }
            $page++;
        }
        return $all;
    }

    /**
     * Update issue fields (labels, state, assignees, etc.).
     */
    public function get_issue(string $owner, string $repo, int $number): array {
        return $this->request('GET', "/repos/{$owner}/{$repo}/issues/{$number}");
    }

    public function update_issue(string $owner, string $repo, int $number, array $data): array {
        return $this->request('PATCH', "/repos/{$owner}/{$repo}/issues/{$number}", $data);
    }

    // -------------------------------------------------------------------------
    // Labels
    // -------------------------------------------------------------------------

    public function get_labels(string $owner, string $repo): array {
        return $this->request('GET', "/repos/{$owner}/{$repo}/labels?per_page=100");
    }

    public function create_label(string $owner, string $repo, string $name, string $color): array {
        return $this->request('POST', "/repos/{$owner}/{$repo}/labels", [
            'name'  => $name,
            'color' => $color,
        ]);
    }
}
