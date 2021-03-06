<?php

namespace MarketPlex;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    // use SoftDeletes;

    protected $table = 'products';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['mrp', 'status'];

    const STATUS_FLOWS = [
        'ON_APPROVAL', 'UPLOAD_FAILED', 'APPROVED', 'REJECTED', 'OUT_OF_STOCK', 'AVAILABLE', 'NOT_AVAILABLE', 'ON_SHIPPING', 'REMOVED', 'COMING_SOON', 'SOLD', 'ORDERED'
    ];

    const VIEW_TYPES = [ 
        'group' => [
            'dropdown' => 'Dropdown',
            // 'checkboxes' => 'Checkboxes',
            'options' => 'Radio Controllers',
            'spinner' => 'Spinners'
        ],
        'single' => [
            // 'selectbox' => 'Selectbox',
            'label' => 'Label',
            'input' => 'Input Box'
        ]
    ];

    const EXISTANCE_TYPE = [ 'PHYSICAL' => 'Physical Product', 'DOWNLOADABLE' => 'Downloadable Product' ];
    
    protected $casts = [
        'special_specs' => 'json'
    ];

    const MAX_AVAILABLE_QUANTITY = 15;
    const MIN_AVAILABLE_QUANTITY = 1;
	 
    public function user()
    {
        return $this->belongsTo('MarketPlex\User');
    }

    public function store()
    {
        return $this->belongsTo('MarketPlex\Store');
    }

    /**
     * Get all of the products' product medias.
     */
    public function medias()
    {
        return $this->morphMany(ProductMedia::class, 'mediable');
    }

    public function delete()
    {
        foreach($this->medias as $media)
        {
            $media->delete(); // call the ProductMedia delete()
        }
        
        return parent::delete();
    }

    public function saveMedias(array $data)
    {
        $success = true;
        if(collect($data)->has('uploaded_files') && !collect($data['uploaded_files'])->isEmpty())
        {
            $newImageCount = count($data['uploaded_files']);
            foreach($data['uploaded_files'] as $file)
            {
                $isVideo = ProductMedia::isMedia($file->getMimeType(), 'VIDEO');
                $productMedia = new ProductMedia();
                $productMedia->is_public = $data['is_public'];
                $productMedia->url = $file->getRealPath();
                $productMedia->media_type = $isVideo ? 'VIDEO' : 'IMAGE';
                $productMedia->title = $file->getBasename();

                $success = $this->medias()->save($productMedia);

                $imagesExisting = $this->medias->where('is_embed', false)->where('media_type', 'IMAGE');
                if($imagesExisting->count() > 4)
                {
                    $imagesExisting->first()->delete();
                }
            }
        }
        // $hasEmbed = $data['has_embed_video'];
        if(collect($data)->has('embed_url') && $data['embed_url'])
        {   
            $productMedia = $this->hasEmbedVideo() ? $this->videoEmbedUrl()['media'] : new ProductMedia();
            $productMedia->is_embed = true;
            $productMedia->is_public = $data['is_public'];
            $productMedia->url = $data['embed_url'];
            $productMedia->media_type = 'VIDEO';
            $productMedia->title = ProductMedia::uuid();

            $success = $this->medias()->save($productMedia);
        }
        return $success;
    }

    private function thumbnailMediaUrl()
    {
        foreach($this->medias as $media)
        {
            if($media->media_type == 'IMAGE')
                return [ 'is_default' => false, 'title' => $media->title ];
        }
        return [ 'is_default' => true, 'title' => (ProductMedia::IMAGES_PATH_PUBLIC . ProductMedia::DEFAUL_IMAGE) ];
    }

    public function hasEmbedVideo()
    {
        return !$this->videoEmbedUrl()['is_default'];
    }

    public function videoEmbedUrl()
    {
        $embedMedia = $this->medias->where('is_embed', true)->where('media_type', 'VIDEO')->first();

        if($embedMedia)
            return [ 'is_default' => false, 'url' => $embedMedia->url, 'media' => $embedMedia ];
        return [ 'is_default' => true, 'url' => '' ];
    }

    public function thumbnail()
    {
        $title = $this->thumbnailMediaUrl()['title'];
        $isDefaulImage = $this->thumbnailMediaUrl()['is_default'];
        return $isDefaulImage ? $title : route('user::products.medias.image', [ 'file_name' => $title ] );
    }

    public function specialSpecs()
    {
        $specs = [];
        $id = 1;
        foreach (json_decode($this->special_specs) as $key => $value) {
            $specs[$key] = collect($value)->merge([ 'id' => $id++ ])->toArray();
        }
        return $specs;
    }

    public function images()
    {
        $images = $this->medias->where('is_embed', false)->where('media_type', 'IMAGE');
        /*Sk Asadur Rahman Script*/
        foreach($images as $image){
            $_location = explode('_',$image->title);
            if(count($_location) > 1){
                $image->_image_position = $_location[1]; // runtime added properties
            }
        }
        /*EOS*/
        $firstTime = true;
        while($images->count() < 4 && $firstTime)
        {
            $image = new ProductMedia();
            $image->is_public = true;
            $image->url = $this->defaultImage();
            $image->media_type = 'IMAGE';
            $image->title = ProductMedia::uuid();
            $image->is_embed = true; // THIS BOOLEAN IS SET TO CHECK IF IT'S A DEFAULT IMAGE OR NOT
            $image->_image_position = false; //Added by Asad
            $images->push($image);
            $firstTime = false;
        }
        return $images;
    }

    public function previewImage($index)
    {
        $image = $this->images()->where('_image_position', $index+1,false);
        if($image->isEmpty()){
            $image = $this->images()->where('_image_position', false,false);
        }
        foreach($image as $image); // Added by Asad
        return $image->is_embed ? $image->url : route('user::products.medias.image', [ 'file_name' => $image->title ]);
    }
    // getImageURL() added by Asad
    public function getImageURL($index)
    {
        $image = $this->images()->where('_image_position', $index+1,false);
        if($image->isEmpty()){
            $image = $this->images()->where('_image_position', false,false);
        }
        foreach($image as $image);
        return $image->url != $this->defaultImage() ? ['title' => $image->title, 'url'=> $image->url] : false;
    }

    public function scopeSearchByTitle($query,$title)
    {
        return $query->where('title', $title)->orWhere('title', 'ilike', '%' . $title . '%');
    }

    public static function defaultImage()
    {
        return (ProductMedia::IMAGES_PATH_PUBLIC . ProductMedia::DEFAUL_IMAGE);
    }

    public function isMine()
    {
        return Auth::user()->products()->find($this->id);
    }

    public function marketProduct()
    {
        return MarketProduct::find($this->market_product_id);
    }

    public function publicMarketProduct()
    {
        return $this->is_public ? $this->marketProduct() : null;
    }

    public function categoryName()
    {
        return  ($this->marketProduct() && $this->marketProduct()->category) ? $this->marketProduct()->category->name : 'Uncategorized';
    }

    public function marketPrice()
    {
        return  ($this->marketProduct()) ? $this->marketProduct()->price : 0.0;
    }

    public function marketManufacturer()
    {
        return  ($this->marketProduct()) ? $this->marketProduct()->manufacturer_name : 'Unknown';
    }


	public function sendApprovals()
	{
		return $this->hasMany('MarketPlex\SendApproval');
	}

    public function approved()
    {
        return $this->status == 'APPROVED';
    }

    public function approve()
    {
        $this->status = 'APPROVED';
        if($this->marketProduct())
        {
            $this->marketProduct()->status = $this->status;
            $this->marketProduct()->save();
        }
        return $this->save();
    }
    
    /**
     * Saves discount calculated MRP
     */
    public function saveDiscountedPrice()
    {
        $this->mrp = $this->discountedPrice();
        return $this->save();
    } 
    
    /**
     * Calculates discounted MRP
     */
    public function discountedPrice()
    {
        return $this->marketProduct()->price * ( 1 -  ( $this->discount / 100.0) );
    }

    public function getStatus()
    {
        switch($this->status)
        {
            case 'OUT_OF_STOCK':    return 'Out of stock';
            case 'ON_APPROVAL':     return 'On Approval';
            case 'APPROVED':        return 'Approved';
            case 'REJECTED':        return 'Rejected';
        }
        return 'Unknown';
    }
}
