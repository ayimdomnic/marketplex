<?php

namespace MarketPlex;

use Illuminate\Database\Eloquent\Model;
use Faker\Factory as StoreFaker;

use Auth;
use MarketPlex\User;

class Store extends Model
{
    //
    protected $table = 'stores';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'name', 'user_id', 'name_as_url', 'sub_domain', 'domain', 'address', 'phone_number', 'description', 'store_type' ]; 
    
    protected $guarded = [];

    public function user()
    {    	
        return $this->belongsTo('MarketPlex\User');
    }

    public function products()
    {
        return $this->hasMany('MarketPlex\Product');
    }

    public static function types()
    {
        return collect([
            ['id' => 'NOT_SURE', 'title' => 'I\'m not sure yet.'],
            ['id' => 'ANIMAL_PET', 'title' => 'Animal &amp; Pet'],
            ['id' => 'ART_ENTERTAINMENT', 'title' => 'Art &amp; Entertainment'],
            ['id' => 'HARDWARE_HOME_DEVELOPMENT', 'title' => 'Hardware or Home/Garden Improvement'],
            ['id' => 'OTHERS', 'title' => 'Others / something else...'],
        ]);
    }

    public function getStatus()
    {
        switch($this->status)
        {
            case 'ON_APPROVAL':     return 'On Apprval';
            case 'APPROVED':        return 'Approved';
            case 'REJECTED':        return 'Rejected';
            case 'REMOVED':         return 'Removed';
        }
        return 'Unknown';
    }

    /**
     * Get the decoded address either from user or it's own
     *
     * @param  string  $value
     * @return string
     */
    public function getAddressAttribute($value)
    {
        return $value ? $value : Auth::user()->address;
    }

    public function getStoreTypeIndex($id)
    {
        foreach ($this->types() as $key => $value)
        {
            if($value['id'] == $id)
            {
                return $key;
            }
        }
        return 0;
    }

    /**
     * Suggest store names with given terms using faker package
     *
     * @param mixed
     * @param integer
     * @return array store names
     */
    public static function suggestFromFaker($inputTerms, $limit)
    {
        $faker = StoreFaker::create();
        session([ 'input_terms' => $inputTerms ]); 
        $nameValidator = function($company) {
            return str_contains( strtolower((string) $company), strtolower(session('input_terms')) );
        };
        $companies = array();
        try
        {
            for ($i=0; $i < $limit; $i++)
                $companies []= $faker->valid($nameValidator)->company;

        } catch (\OverflowException $e) {
            
            session()->forget('input_terms');
            return "";
        }
        return $companies;
    }

    /**
     * Suggest store names with given terms
     *
     * @param mixed
     * @param integer
     * @return array store names
     */
    public static function suggest($inputTerms, $limit)
    {
        $company_liability_types = [ 'Ltd.', 'GmbH', 's.r.o.', 'sp. z o.o.', 'LLC', 'Groups' ];
        // $splitted_terms = preg_split("/[\s]+/", $inputTerms);
        $companies = array();
        foreach ($company_liability_types as $key => $value) {
            $companies []= title_case($inputTerms) . ' ' . date("Y") . ' ' . $company_liability_types[$key];
        }
        return $companies;
    }

    public function getSiteAddress()
    {
        return str_replace('.', '', $this->name_as_url) . '.' . $this->sub_domain . '.' . $this->domain;
    }

    public function getUrl()
    {
        return config('app.url');
    }

    public function getTidyUrl()
    {
        return str_replace('http://', '', $this->getUrl());
    }

    public static function storeCreated()
    {
        return self::count() == 1;    
    }

    public function isBelowStoreCountPolicy()
    {
        return $this->user->stores()->count() == env('STORE_LIMIT_PER_VENDOR', 1);
    }

    public function isAllowedMasterDelete()
    {
        return env('STORE_DELETE_MIN_POLICY_BREAK', false) === true;
    }
}
