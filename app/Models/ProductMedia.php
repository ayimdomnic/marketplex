<?php

namespace MarketPlex;

use Illuminate\Database\Eloquent\Model;

use MarketPlex\MediaUploader\MediaUploader;
use MarketPlex\MediaUploader\ImageUploader;
use MarketPlex\MediaUploader\VideoUploader;

use \Symfony\Component\HttpFoundation\File\UploadedFile;

use File;
use Log;
use MarketPlex\Exception;

class ProductMedia extends Model
{
	const MEDIA_TYPES = ['UNKNOWN', 'IMAGE', 'VIDEO', 'AUDIO'];
    const SUPPORTED_MEDIA_MIMES = [
        'IMAGE' => [ 'png', 'jpeg','jpg', 'gif' ],
        'VIDEO' => [ 'video/avi', 'video/mpeg', 'video/quicktime', 'video/mp4' ],
        'AUDIO' => []
    ];
    const MAX_ALLOWED_IMAGE = 4;
    const MEDIA_CONTEXT = "products";
    const DEFAUL_IMAGE = 'default_product.jpg';
    const IMAGES_PATH_PUBLIC = '/images/products/';

    protected $table = 'product_medias';

    /**
     * Get all of the owning mediable models.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    private function isRemovable()
    {
        return !$this->is_embed || !$this->is_public;
    }

    public function delete()
    {
        if($this->isRemovable())
        {
            File::delete($this->url);

            if(File::exists($this->url))
                return false;
        }
        return parent::delete();
    }

    public static function uuid()
    {
        $faker = \Faker\Factory::create('en_GB');
        $faker->addProvider(new \Faker\Provider\Uuid($faker));
        return $faker->unique()->uuid;
    }

    private static function uploadSingle($requestedFile)
    {
        $mediaUploader = self::isMedia($requestedFile->getClientMimeType(), 'VIDEO') ? new VideoUploader(self::MEDIA_CONTEXT) : new ImageUploader(self::MEDIA_CONTEXT);
        $mediaUploader->validate($requestedFile);
        if($mediaUploader->fails())
        {
            return $mediaUploader->errors();
        }
        $image_name = ProductMedia::uuid(). '_'.$requestedFile->_image_position.'_' . '.' . $requestedFile->getClientOriginalExtension();
        $serverFile = $mediaUploader->upload($image_name);
        if($mediaUploader->fails())
        {
            return $mediaUploader->errors();
        }
        return $serverFile;
    }

    public static function upload(array $requestedFiles)
    {
        $serverFiles = [];
        $errors = [];
        foreach($requestedFiles as $key => $requestedFile)
        {
            $requestedFile->_image_position = $key;
            $requestedFileName = $requestedFile->getClientOriginalName();
            $serverEntity = self::uploadSingle($requestedFile);
            if(!is_array($serverEntity))
            {
                $serverFiles []= $serverEntity;
                continue;
            }
            Log::error('[' . config('app.vendor') . '][ Uploading media failed for ' . $requestedFileName . ']');
            $errors []= $serverEntity;
        }
        return [ 'server_files' => $serverFiles, 'errors' => $errors  ];
    }

    public static function tidyMimes($mediaType)
    {
        $dirtyChars = '[]""';
        $mimes = trim(collect(ProductMedia::SUPPORTED_MEDIA_MIMES[$mediaType])->toJson(), $dirtyChars);
        $dirtyChars = '","';
        $mimes = str_replace($dirtyChars, ",", $mimes);
        if($mediaType == 'VIDEO')
            $dirtyChars = "\/";
        return str_replace($dirtyChars, "/", $mimes);
    }

    public static function getMediaRule($mediaType)
    {
        $size_limit_rule = '|max:' . (UploadedFile::getMaxFilesize()/ 1000);
        if($mediaType == 'IMAGE')
            return 'image|mimes:' . self::tidyMimes('IMAGE') . $size_limit_rule;
        return 'mimetypes:' . self::tidyMimes('VIDEO') . $size_limit_rule;
    }

    /**
     * @param $mediaMime        the media MIME
     * @param $queryMediaType   the query of a type
     */
    public static function isMedia($mediaMime, $queryMediaType)
    {
        foreach(ProductMedia::SUPPORTED_MEDIA_MIMES as $mediaType => $mimes)
        {
            if($mediaType == $queryMediaType)
            {
                foreach($mimes as $mime)
                {
                    if($mime == strtolower($mediaMime))
                        return true;
                }   
            }
        }
        return false;
    }

    public static function isValidURL($url)
    {
        return collect(parse_url($url))->toArray()['path'] == "" ? false : true;
    }

    public static function getStoragePath($mediaType)
    {
        return storage_path(MediaUploader::STORAGE_PATH . ($mediaType == 'IMAGE' ? ImageUploader::IMAGE_STORAGE_PATH : VideoUploader::IMAGE_STORAGE_PATH) . '/' . self::MEDIA_CONTEXT . '/');
    }
}
