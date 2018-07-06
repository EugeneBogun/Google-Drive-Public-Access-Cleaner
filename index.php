<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Drive API PHP Quickstart');
    $client->setScopes([
        Google_Service_Drive::DRIVE,
        Google_Service_Drive::DRIVE_FILE,
        Google_Service_Drive::DRIVE_METADATA,
//        Google_Service_Drive::DRIVE_METADATA_READONLY,
        Google_Service_Drive::DRIVE_APPDATA,
    ]);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory('access.json');
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }

    return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path)
{
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }

    return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);

// Print the names and IDs for up to 10 files.
$optParams = array(
//    'pageSize' => 10,
//    'fields' => 'nextPageToken, files(id, name, ownedByMe)',
    'includeTeamDriveItems' => false,
);
$results = $service->files->listFiles([
    'pageSize' => 1000,
    'includeTeamDriveItems' => false,
    'fields'                => 'nextPageToken, files(id, name, ownedByMe, permissionIds)',
]);
$count = 0;
$files = [];
if (count($results->getFiles()) == 0) {
    print "No files found.\n";
} else {
    print "Files:\n";
    while ($results->getFiles()) {
        foreach ($results->getFiles() as $file) {
            if(array_key_exists($file->getId(), $files)) {
                printf("\nError file duplicated: %s\n", $file->getName());
                exit;
            }
            $files[$file->getId()] = $file->getId();
            printf("Files processed: %s", $count);
            $count++;
            if ($file->getOwnedByMe() && in_array('anyoneWithLink', (array)$file->getPermissionIds())) {
                printf("\n%s (%s)\n", $file->getName(), $file->getId());
                $service->permissions->delete($file->getId(), 'anyoneWithLink');
            }
            printf("\r");
        }
        $results = $service->files->listFiles([
            'includeTeamDriveItems' => false,
            'pageToken'             => $results->getNextPageToken(),
        ]);
    }
}
