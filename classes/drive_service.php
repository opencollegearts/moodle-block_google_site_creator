<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Google Drive API service client.
 *
 * @package   block_google_site_creator
 * @copyright 2026 Paul Vincent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_google_site_creator;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Drive API service (copy file, manage folder, set permissions).
 * Uses service account JWT; no Composer dependency.
 *
 * @package   block_google_site_creator
 */
class drive_service {

    /** @var array Service account JSON (decoded) */
    protected $credentials;

    /** @var string|null Cached access token */
    protected $accesstoken = null;

    /** @var int|null Token expiry */
    protected $tokenexpiry = null;

    /** @var string|null Delegated Workspace user for domain-wide delegation */
    protected $delegateduser = null;

    /** Drive API base URL */
    const DRIVE_API = 'https://www.googleapis.com/drive/v3';

    /** OAuth2 token endpoint */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Drive scope (full) */
    const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

    public function __construct(array $credentials, ?string $delegateduser = null) {
        $this->credentials = $credentials;
        $this->delegateduser = $delegateduser;
    }

    /**
     * Get an OAuth2 access token using service account JWT.
     *
     * @return string
     * @throws \moodle_exception
     */
    protected function get_access_token(): string {
        if ($this->accesstoken !== null && $this->tokenexpiry !== null && time() < $this->tokenexpiry - 60) {
            return $this->accesstoken;
        }

        $now = time();
        $payload = [
            'iss' => $this->credentials['client_email'] ?? null,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => self::SCOPE_DRIVE,
        ];
        $subject = trim((string)($this->delegateduser ?? ''));
        if ($subject !== '') {
            $payload['sub'] = $subject;
        }
        if (empty($payload['iss'])) {
            throw new \moodle_exception('error', 'block_google_site_creator', '', null, 'Service account JSON missing client_email');
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64url_encode(json_encode($header)),
            $this->base64url_encode(json_encode($payload)),
        ];
        $signing = implode('.', $segments);

        $key = $this->credentials['private_key'] ?? null;
        if (empty($key)) {
            throw new \moodle_exception('error', 'block_google_site_creator', '', null, 'Service account JSON missing private_key');
        }
        $key = str_replace(["\r\n", "\n", "\r"], "\n", $key);
        $pkey = openssl_pkey_get_private($key);
        if ($pkey === false) {
            throw new \moodle_exception('error', 'block_google_site_creator', '', null, 'Invalid service account private key');
        }
        $sig = '';
        openssl_sign($signing, $sig, $pkey, OPENSSL_ALGO_SHA256);
        $segments[] = $this->base64url_encode($sig);
        $jwt = implode('.', $segments);

        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], '', '&', PHP_QUERY_RFC3986);

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $response = $curl->post(self::TOKEN_URL, $body);
        $code = $curl->get_info()['http_code'] ?? 0;
        if ($code !== 200) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator', '', null, $response);
        }
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }
        $this->accesstoken = $data['access_token'];
        $this->tokenexpiry = $now + (int)($data['expires_in'] ?? 3600);
        return $this->accesstoken;
    }

    private function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Validate and encode a Drive file/permission id for use in a URL path segment.
     *
     * @param string $id
     * @return string
     * @throws \moodle_exception
     */
    protected function encode_drive_id(string $id): string {
        $id = trim($id);
        if ($id === '' || !preg_match('/^[A-Za-z0-9_-]{10,256}$/', $id)) {
            throw new \moodle_exception('invaliddriveid', 'block_google_site_creator');
        }
        return rawurlencode($id);
    }

    /**
     * Make an authenticated request to Drive API.
     *
     * @param string $method GET|POST|PATCH|DELETE
     * @param string $endpoint e.g. /drive/v3/files (path only)
     * @param array|null $body JSON body
     * @return array Decoded JSON
     * @throws \moodle_exception
     */
    protected function request(string $method, string $endpoint, ?array $body = null): array {
        $url = $endpoint;
        if (strpos($url, 'http') !== 0) {
            $url = self::DRIVE_API . (strpos($endpoint, '/') === 0 ? $endpoint : '/' . $endpoint);
        }
        $token = $this->get_access_token();
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        if ($method === 'GET') {
            $response = $curl->get($url);
        } elseif ($method === 'POST') {
            $response = $curl->post($url, $body !== null ? json_encode($body) : '{}');
        } elseif ($method === 'PATCH') {
            $response = $curl->patch($url, $body !== null ? json_encode($body) : '{}');
        } else {
            $response = $curl->delete($url);
        }
        $code = $curl->get_info()['http_code'] ?? 0;
        if ($code < 200 || $code >= 300) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator', '', null, $response);
        }
        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : [];
    }

    /**
     * Copy a Drive file (template) with a new name and optional parent folder.
     *
     * @param string $fileid Template file ID
     * @param string $newname Title for the copy
     * @param string|null $parentid Optional folder ID to place the copy in
     * @return array With keys id, name, webViewLink (or webContentLink)
     * @throws \moodle_exception
     */
    public function copy_file(string $fileid, string $newname, ?string $parentid = null): array {
        $body = ['name' => $newname];
        if ($parentid !== null && $parentid !== '') {
            // Parent IDs in the JSON body are not path segments; still reject unsafe values.
            if (!preg_match('/^[A-Za-z0-9_-]{10,256}$/', trim($parentid))) {
                throw new \moodle_exception('invaliddriveid', 'block_google_site_creator');
            }
            $body['parents'] = [trim($parentid)];
        }
        $result = $this->request(
            'POST',
            '/files/' . $this->encode_drive_id($fileid) . '/copy?supportsAllDrives=true&fields=id,name,webViewLink,webContentLink',
            $body
        );
        return $result;
    }

    /**
     * Update metadata on a Drive file.
     *
     * @param string $fileid
     * @param array $metadata
     * @return array
     */
    public function update_file_metadata(string $fileid, array $metadata): array {
        return $this->request(
            'PATCH',
            '/files/' . $this->encode_drive_id($fileid) . '?supportsAllDrives=true&fields=id,name,description,webViewLink,webContentLink',
            $metadata
        );
    }

    /**
     * Find or create a folder in the service account's Drive (or shared Drive) by name.
     * Searches for a folder with the given name in the root; if not found, creates it.
     * Note: Service account has its "own" Drive; if you need user's Drive, use domain-wide delegation and impersonate.
     *
     * @param string $foldername Course short name
     * @param string|null $parentid Parent folder ID (null = root)
     * @return string Folder ID
     * @throws \moodle_exception
     */
    public function ensure_folder_by_name(string $foldername, ?string $parentid = null): string {
        $q = "name = '" . str_replace("'", "\\'", $foldername) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        if ($parentid) {
            $q .= " and '" . str_replace("'", "\\'", $parentid) . "' in parents";
        } else {
            $q .= " and 'root' in parents";
        }
        $list = $this->request(
            'GET',
            '/files?q=' . urlencode($q) . '&fields=files(id,name)&pageSize=1&supportsAllDrives=true&includeItemsFromAllDrives=true'
        );
        if (!empty($list['files'][0]['id'])) {
            return $list['files'][0]['id'];
        }
        $body = [
            'name' => $foldername,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];
        if ($parentid) {
            $body['parents'] = [$parentid];
        }
        $created = $this->request('POST', '/files?fields=id&supportsAllDrives=true', $body);
        if (empty($created['id'])) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }
        return $created['id'];
    }

    /**
     * Add a permission to a file (share with user/group). No notification email.
     *
     * @param string $fileid Drive file ID
     * @param string $emailaddress Email to share with
     * @param string $role reader|writer|commenter
     * @param string $type user|group
     * @param array $queryparams Extra Drive API query parameters
     * @param bool $sendnotificationemail Whether Google should email the recipient
     * @return string|null New permission id when returned by Drive
     * @throws \moodle_exception
     */
    public function add_permission(
        string $fileid,
        string $emailaddress,
        string $role = 'writer',
        string $type = 'user',
        array $queryparams = [],
        bool $sendnotificationemail = false
    ): ?string {
        $query = array_merge(
            [
                'fields' => 'id',
                'supportsAllDrives' => 'true',
                'sendNotificationEmail' => $sendnotificationemail ? 'true' : 'false',
            ],
            $queryparams
        );
        $body = [
            'type' => $type,
            'role' => $role,
            'emailAddress' => $emailaddress,
        ];
        $result = $this->request(
            'POST',
            '/files/' . $this->encode_drive_id($fileid) . '/permissions?' . http_build_query($query),
            $body
        );
        $permissionid = trim((string)($result['id'] ?? ''));
        return $permissionid !== '' ? $permissionid : null;
    }

    /**
     * List permissions for a Drive file.
     *
     * @param string $fileid
     * @return array
     */
    public function list_permissions(string $fileid): array {
        $response = $this->request(
            'GET',
            '/files/' . $this->encode_drive_id($fileid) .
                '/permissions?supportsAllDrives=true&fields=permissions(id,type,emailAddress,role)'
        );
        return $response['permissions'] ?? [];
    }

    /**
     * Delete a permission by id.
     *
     * @param string $fileid
     * @param string $permissionid
     * @return void
     */
    public function delete_permission(string $fileid, string $permissionid): void {
        $this->request(
            'DELETE',
            '/files/' . $this->encode_drive_id($fileid) . '/permissions/' .
                rawurlencode(trim($permissionid)) . '?supportsAllDrives=true'
        );
    }

    /**
     * Update an existing permission role.
     *
     * @param string $fileid
     * @param string $permissionid
     * @param string $role
     * @param array $queryparams
     * @return void
     */
    public function update_permission(string $fileid, string $permissionid, string $role, array $queryparams = []): void {
        $query = array_merge(['fields' => 'id', 'supportsAllDrives' => 'true'], $queryparams);
        $this->request(
            'PATCH',
            '/files/' . $this->encode_drive_id($fileid) . '/permissions/' .
                rawurlencode(trim($permissionid)) . '?' . http_build_query($query),
            ['role' => $role]
        );
    }

    /**
     * Find a permission ID for a user email on a file.
     *
     * @param string $fileid
     * @param string $email
     * @return string|null
     */
    public function find_permission_id_for_email(string $fileid, string $email): ?string {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        foreach ($this->list_permissions($fileid) as $permission) {
            $permemail = strtolower(trim((string)($permission['emailAddress'] ?? '')));
            if ($permemail === $email) {
                $permissionid = (string)($permission['id'] ?? '');
                if ($permissionid !== '') {
                    return $permissionid;
                }
            }
        }
        return null;
    }

    /**
     * Transfer ownership of a Drive file/folder to another user in the same Workspace domain.
     *
     * @param string $fileid
     * @param string $newowneremail
     * @param bool $movetonewownersroot
     * @return void
     */
    public function transfer_ownership(string $fileid, string $newowneremail, bool $movetonewownersroot = false): void {
        $newowneremail = trim($newowneremail);
        if ($newowneremail === '') {
            throw new \moodle_exception('createfailed', 'block_google_site_creator', '', null, 'Missing new owner email');
        }

        // transferOwnership must be a query parameter, not part of the JSON body.
        $query = 'supportsAllDrives=true&transferOwnership=true&sendNotificationEmail=true&fields=id';
        if ($movetonewownersroot) {
            $query .= '&moveToNewOwnersRoot=true';
        }

        $encodedfileid = $this->encode_drive_id($fileid);
        $permissionid = $this->find_permission_id_for_email($fileid, $newowneremail);
        if ($permissionid !== null) {
            $this->request(
                'PATCH',
                '/files/' . $encodedfileid . '/permissions/' . rawurlencode($permissionid) . '?' . $query,
                ['role' => 'owner']
            );
            return;
        }

        $this->request(
            'POST',
            '/files/' . $encodedfileid . '/permissions?' . $query,
            [
                'type' => 'user',
                'role' => 'owner',
                'emailAddress' => $newowneremail,
            ]
        );
    }
}
