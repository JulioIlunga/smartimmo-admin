<?php

namespace App\Service;

use Aws\S3\S3Client;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ImageUploaderService
{
    private $binaryLoader;
    private $filterManager;
    private $s3Client;
    private $imageTempDir;

    public function __construct(
        LoaderInterface $binaryLoader,
        FilterManager $filterManager,
        S3Client $s3Client,
        ParameterBagInterface $params,
    ) {
        $this->binaryLoader = $binaryLoader;
        $this->filterManager = $filterManager;
        $this->s3Client = $s3Client;
        $this->imageTempDir = $params->get('kernel.project_dir') . '/public/uploads/tmp';
    }

    public function uploadAndResizeImageToS3(UploadedFile $file): string
    {
        // 1. Move file to public/uploads/tmp/
        $filename = uniqid() . '.' . $file->guessExtension();
        $webPath = 'uploads/tmp/' . $filename;
        $absolutePath = $this->imageTempDir . '/' . $filename;

        if (!file_exists($this->imageTempDir)) {
            mkdir($this->imageTempDir, 0777, true);
        }

        // Move file
        $file->move($this->imageTempDir, $filename);

        // 2. Load image using LiipImagine's Loader
        $binary = $this->binaryLoader->find($webPath);

        // 3. Apply filter (resize)
        $filteredBinary = $this->filterManager->applyFilter($binary, 's3_upload');

        // 4. Save filtered image to temporary path
        $resizedPath = $this->imageTempDir . '/resized_' . $filename;
        file_put_contents($resizedPath, $filteredBinary->getContent());

        // 5. Optimize the resized image using Spatie Image Optimizer
        $optimizer = OptimizerChainFactory::create();
        $optimizer->optimize($resizedPath);

        // 6. Upload to AWS S3
        $result = $this->s3Client->putObject([
            'Bucket'     => 'smart-technology',
            'Key'        => 'smart-immo-listing/' . $filename,
            'SourceFile' => $resizedPath,
            'ACL'        => 'public-read', // Adjust as necessary
            'ContentType'=> mime_content_type($resizedPath),
        ]);

        // 7. Clean up temporary files
        unlink($absolutePath);
        unlink($resizedPath);

        // Return S3 URL of the uploaded file
        return $result['ObjectURL'];
    }
}
