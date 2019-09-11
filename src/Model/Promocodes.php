<?php

namespace Trexology\Promocodes\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Promocodes extends Model
{

	protected $guarded = [
    'id','created_at','updated_at'
  ];

  protected $table = 'promocodes';

  public $rules = [
    'code' => 'required'
  ];

	/**
     * Generated codes will be saved here
     * to be validated later
     *
	 * @var array
	 */
	// protected $codes = [];

	/**
     * Length of code will be calculated
     * from asterisks you have set as
     * mas in your config file
     *
	 * @var int
	 */
	protected $length;

	/**
	 * Promocodes constructor.
	 */
	public function __construct()
	{
		$this->length = substr_count(config('promocodes.mask'), '*');
		parent:: __construct();
	}

	/**
     * Here will be generated single code
     * using your parameters from config
     *
	 * @return string
	 */
	public function randomize()
	{
		$characters = config('promocodes.characters');
		$separator  = config('promocodes.separator');
		$mask       = config('promocodes.mask');
		$prefix     = config('promocodes.prefix');
		$suffix     = config('promocodes.suffix');

		$random = [];
		$code   = '';

		for ($i = 1; $i <= $this->length; $i++) {
			$character = $characters[rand(0, strlen($characters) - 1)];
			$random[]  = $character;
		}

		shuffle($random);

		if ($prefix !== false) {
			$code .= $prefix . $separator;
		}

		for ($i = 0; $i < count($random); $i++) {
			$mask = preg_replace('/\*/', $random[$i], $mask, 1);
		}

		$code .= $mask;

		if ($suffix !== false) {
			$code .= $separator . $suffix;
		}

		return $code;
	}

	// /**
  //    * Your code will be validated to
  //    * be unique for one request
  //    *
	//  * @param $collection
	//  * @param $new
	//  *
	//  * @return bool
	//  */
	public function validate($new)
	{
		// if (count($collection) == 0 && count($this->codes) == 0) return true;

		$count = Promocodes::where('code', $new)->count();
		if ($count == 0) {
			return true;
		}
		else{
			return false;
		}

		// $combined = array_merge($collection, $this->codes);
		//
		// return !in_array($new, $combined);
	}

	/**
     * Generates promocodes as many as you wish
     *
	 * @param int $amount
	 *
	 * @return array
	 */
	public function generate($amount = 1)
	{
		$collection = [];

		for ($i = 1; $i <= $amount; $i++) {
			$random = $this->randomize();

			while (!$this->validate($random)) {
				$random = $this->randomize();
			}

			$collection[] = $random;
		}

		return $collection;
	}

	/**
	 * Save promocodes into database
     * Successfull insert returns generated promocodes
     * Fail will return NULL
	 *
	 * @param int $amount
	 *
	 * @return static
	 */
	public function generateAndSave($amount = 1, $reward = null)
	{
		$data = collect([]);

		foreach ($this->generate($amount) as $key => $code) {
			$promo = new Promocodes();
			$promo->code = $code;
			$promo->reward = $reward;
			$promo->save();
			$data->push($promo);
		}

		return $data;
	}

	/**
		 * Generates promocodes with specific code
		 *
	 * @param string $code
	 * @param double $reward
	 *
	 * @return Promocodes / false
	 */
	public function generateCodeName($code, $reward = null)
	{
		if ($this->validate($code)) {
			$this->code = $code;
			$this->reward = $reward;
			$this->save();
			return $this;
		}
		else{
			return false;
		}
	}

	/**
     * Check promocode in database if it is valid
     *
	 * @param $code
	 *
	 * @return bool
	 */
	public function check($code)
	{
		return Promocodes::where('code', $code)
		// ->whereNull('is_used')
		->where('quantity', '!=' , 0)
		->where(function($q) {
					 $q->whereDate('expiry_date', '<' , Carbon::today())
						 ->orWhereNull('expiry_date');
		})
		->count() > 0;
	}

	/**
     * Apply promocode to user that it's used from now
     *
	 * @param $code
	 *
	 * @return bool
	 */
	public function apply($code, $hard_check = false)
	{
		$record = Promocodes::where('code', $code)
		->whereNull('is_used')
		->where('quantity', '!=' , 0) // -1 for infinite
		->where(function($q) {
					 $q->whereDate('expiry_date', '<' , Carbon::today())
						 ->orWhereNull('expiry_date');
		})
		->first();
		//
		if ($record) {
			if ($record->quantity > 0) {
				$record->quantity--;
			}
			$record->is_used = Carbon::now();

			if ($record->save()) {
				if ($hard_check) {
					return $record->reward;
				} else {
					return true;
				}
			}
		}

		return false;
	}
}
