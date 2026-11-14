<?php

namespace Osaris\Monzo\Resources;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Osaris\Monzo\Client;
use Osaris\Monzo\Data\Attachment;
use Osaris\Monzo\Exceptions\MonzoException;
use SplFileInfo;

class Attachments extends Resource
{
    public function __construct(
        Client $client,
        ?string $defaultAccountId = null,
        protected string $uploadMethod = 'PUT',
    ) {
        parent::__construct($client, $defaultAccountId);
    }

    /**
     * Upload a file and attach it to a transaction in one go.
     *
     * @param  string|SplFileInfo|UploadedFile  $file  A path, a file object, or raw contents (give $fileName and $fileType)
     */
    public function upload(
        string $transactionId,
        string|SplFileInfo $file,
        ?string $fileName = null,
        ?string $fileType = null,
    ): Attachment {
        [$contents, $fileName, $fileType] = $this->resolveFile($file, $fileName, $fileType);

        $target = $this->requestUpload($fileName, $fileType, strlen($contents));

        $this->sendToUploadUrl($target['upload_url'], $contents, $fileType);

        return $this->register($transactionId, $target['file_url'], $fileType);
    }

    /**
     * POST /attachment/upload — step 1: get a temporary upload URL.
     *
     * @return array{file_url: string, upload_url: string}
     */
    public function requestUpload(string $fileName, string $fileType, int $contentLength): array
    {
        return $this->client->post('/attachment/upload', [
            'file_name' => $fileName,
            'file_type' => $fileType,
            'content_length' => $contentLength,
        ]);
    }

    /**
     * Step 2: send the bytes to the upload_url. No Monzo auth header is sent.
     */
    public function sendToUploadUrl(string $uploadUrl, string $contents, string $fileType): void
    {
        $response = Http::withBody($contents, $fileType)
            ->send(strtoupper($this->uploadMethod), $uploadUrl, ['body' => $contents]);

        if ($response->failed()) {
            throw MonzoException::fromResponse($response);
        }
    }

    /**
     * POST /attachment/register — attach a file URL to a transaction.
     *
     * Works for files you host yourself too, not just ones uploaded to Monzo.
     */
    public function register(string $transactionId, string $fileUrl, string $fileType): Attachment
    {
        $response = $this->client->post('/attachment/register', [
            'external_id' => $transactionId,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
        ]);

        return Attachment::fromArray($response['attachment']);
    }

    /**
     * POST /attachment/deregister
     */
    public function deregister(string $attachmentId): void
    {
        $this->client->post('/attachment/deregister', ['id' => $attachmentId]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function resolveFile(string|SplFileInfo $file, ?string $fileName, ?string $fileType): array
    {
        if ($file instanceof UploadedFile) {
            return [
                $file->get(),
                $fileName ?? $file->getClientOriginalName(),
                $fileType ?? $file->getMimeType() ?? 'application/octet-stream',
            ];
        }

        $path = $file instanceof SplFileInfo ? $file->getPathname() : (is_file($file) ? $file : null);

        if ($path !== null) {
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new InvalidArgumentException("Could not read [{$path}].");
            }

            return [
                $contents,
                $fileName ?? basename($path),
                $fileType ?? (mime_content_type($path) ?: 'application/octet-stream'),
            ];
        }

        if ($fileName === null || $fileType === null) {
            throw new InvalidArgumentException('Pass $fileName and $fileType when uploading raw file contents.');
        }

        return [$file, $fileName, $fileType];
    }
}
