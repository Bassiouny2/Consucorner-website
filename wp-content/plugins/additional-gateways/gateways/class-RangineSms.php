<?php

/**
* Main Rangine SMS API Class
*/
class digits_rangineSms {

  /*
  * Version of this class
  */
  private $Mversion = '1.5';

  /** 
  * Rangine SMS API Username
  * 
  * @var string
  */
  private $username;
  
  /** 
  * Rangine SMS API Password
  * 
  * @var string
  */
  private $Password;


  /**
  * From line used on text messages
  *
  * @var string (11 characters or 12 numbers)
  */
  private $sender;

  /**
  * Panel domain
  */
  private $domain = 'sms.rangine.ir';


  /**
  */
  public function __construct($arg ,array $options = array()) {
    if (empty($arg)) {
      return ("Username and Password and Sender can't be blank");
    } else {
      $this->username = $arg['username'];
      $this->password = $arg['password'];
      $this->sender = $arg['sender'];
      if(isset($arg['domain']) && $arg['domain'] !== '') $this->domain = $arg['domain'];
    }
  }

  /**
  * Send some text messages
  */
  public function send(array $sms) {

    if (!is_array($sms)) {
      return ("sms parameter must be an array");
    }

	$url = $this->domain.'/services.jspd';
	$sms['message'] = str_replace('pcode:','patterncode:',strip_tags(trim($sms['message'])));
	if (substr($sms['message'], 0, 11) === "patterncode") {	
		return $this->sendPattern($sms,$url);
	}
	$param = array
				(
					'uname'=> $this->username,
					'pass'=>$this->password,
					'from'=>$this->sender,
					'message'=> $sms['message'],
					'to'=>json_encode(array($sms['to'])),
					'op'=>'send',
				);
	return $this->rangine_curl($url,$param);
  }


  /**
  * Send sample message
  */
  public function sendInternational(array $sms) {
	$url = 'rangine.ir/smsapi/international/send.php';
	$param = array
		(
			'uname'=> $this->username,
			'api'=>$sms['internationalapi'],
			'message'=> $sms['message'],
			'shop'=> get_option('blogname'),
			'domain'=> get_option('siteurl'),
			'WPtemplate'=>get_option('template'),
			'Wooversion'=>get_option('woocommerce_version'),
			'Mversion'=>$this->Mversion,
			'otp'=>$sms['otp'],
			'mobile'=>$sms['to'],
		);

	return $this->rangine_curl($url,$param);
  }
  public function sendPattern(array $sms,$url) {
	$text = $sms['message'];
	$to = array($sms['to']);
	$splited = explode(';', $text);
	$pattern_code_array = explode(':', $splited[0]);
	$pattern_code = $pattern_code_array[1];
	unset($splited[0]);
	$resArray = array();
	foreach ($splited as $parm) {
		$splited_parm = explode(':', $parm,2);
		$resArray[$splited_parm[0]] = $splited_parm[1];
	}
	$user = $this->username;
	$pass = $this->password;
	$fromNum = $this->sender;
	$toNum = $to;
	$input_data = $resArray;
	$param = array
		(
			'uname'=> $this->username,
			'pass'=>$this->password,
			'from'=>$this->sender,
			'message'=> $sms['message'],
			'to'=>json_encode($to),
			'op'=>'sendPattern',
		);
	$url = $this->domain."/patterns/pattern?username=".urlencode($user)."&password=".urlencode($pass)."&from=".$fromNum."&to=".json_encode($toNum)."&input_data=".urlencode(json_encode($input_data))."&pattern_code=$pattern_code";
	return $this->rangine_curl($url,$param,'GET');
  }

	private function rangine_curl($url, $param,$type= 'POST'){

		$handler = curl_init($url);        
		curl_setopt($handler, CURLOPT_CONNECTTIMEOUT, 5); 
		curl_setopt($handler, CURLOPT_TIMEOUT, 20);
		curl_setopt($handler, CURLOPT_CUSTOMREQUEST,$type);
		if($type == 'POST') curl_setopt($handler, CURLOPT_POSTFIELDS, $param);               
		curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($handler);
		if (curl_errno($handler)) {
			$result = json_encode(array(1000105, 'Failed to connect api: ' . curl_error($handler)));
		}
		return $result;

	}
	/**
	 * لیست خطاهای وب سرویس
	 */
	public function errors_describe($error){
	 $errorCodes = array(
	  '0' => 'عملیات با موفقیت انجام شده است.',
	  '1' => 'متن پیام خالی می باشد.',
	  '2' => 'کاربر محدود گردیده است.',
	  '3' => 'خط به شما تعلق ندارد.',
	  '4' => 'گیرندگان خالی است.',
	  '5' => 'اعتبار کافی نیست.',
	  '7' => 'خط مورد نظر برای ارسال انبوه مناسب نمیباشد.',
	  '9' => 'خط مورد نظر در این ساعت امکان ارسال ندارد.',
	  '98' => 'حداکثر تعداد گیرنده رعایت نشدهه است.',
	  '99' => 'اپراتور خط ارسالی قطع می باشد.',
	  '21' => 'پسوند فایل صوتی نامعتبر است.',
	  '22' => 'سایز فایل صوتی نامعتبر است.',
	  '23' => 'تعداد تالش در پیام صوتی نامعتبر است.',
	  '100' => 'شماره مخاطب دفترچه تلفن نامعتبر می باشد.',
	  '101' => 'شماره مخاطب در دفترچه تلفن وجود دارد.',
	  '102' => 'شماره مخاطب با موفقیت در دفترچه تلفن ذخیره گردید.',
	  '111' => 'حداکثر تعداد گیرنده برای ارسال پیام صوتی رعایت نشده است.',
	  '131' => 'تعداد تالش در پیام صوتی باید یکبار باشد.',
	  '132' => 'آدرس فایل صوتی وارد نگردیده است.',
	  '301' => 'از حرف ویژه در نام کاربری استفاده گردیده است.',
	  '302' => 'قیمت گذاری انجام نگریدهه است.',
	  '303' => 'نام کاربری وارد نگردیده است.',
	  '304' => 'نام کاربری قبال انتخاب گردیده است.',
	  '305' => 'نام کاربری وارد نگردیده است.',
	  '306' => 'کد ملی وارد نگردیده است.',
	  '307' => 'کد ملی به خطا وارد شده است.',
	  '308' => 'شماره شناسنامه نا معتبر است.',
	  '309' => 'شماره شناسنامه وارد نگردیده است.',
	  '310' => 'ایمیل کاربر وارد نگردیده است.',
	  '311' => 'شماره تلفن وارد نگردیده است.',
	  '312' => 'تلفن به درستی وارد نگردیده است.',
	  '313' => 'آدرس شما وارد نگردیده است.',
	  '314' => 'شماره موبایل را وارد نکرده اید.',
	  '315' => 'شماره موبایل به نادرستی وارد گردیده است.',
	  '316' => 'سطح دسترسی به نادرستی وارد گردیده است.',
	  '317' => 'کلمه عبور وارد نگردیده است.',
	  '404' => 'پترن در دسترس نیست.',
	  '455' => 'ارسال در آینده برای کد بالک ارسالی لغو شد.',
	  '456' => 'کد بالک ارسالی نامعتبر است.',
	  '458' => 'کد تیکت نامعتبر است.',
	  '964' => 'شما دسترسی نمایندگی ندارید.',
	  '962' => 'نام کاربری یا کلمه عبور نادرست می باشد.',
	  '963' => 'دسترسی نامعتبر می باشد.',
	  '971' => 'پترن ارسالی نامعتبر است.',
	  '970' => 'پارامتر های ارسالی برای پترن نامعتبر است.',
	  '972' => 'دریافت کننده برای ارسال پترن نامعتبر می باشد.',
	  '992' => 'ارسال پیام از ساعت 8 تا 23 می باشد.',
	  '993' => 'دفترچه تلفن باید یک آرایه باشد',
	  '994' => 'لطفا تصویری از کارت بانکی خود را از منو مدارک ارسال کنید',
	  '995' => 'جهت ارسال با خطوط اشتراکی سامانه، لطفا شماره کارت بانکیه خود را به دلیل تکمیل فرایند احراز هویت از بخش ارسال مدارک ثبت نمایید.',
	  '996' => 'پترن فعال نیست.',
	  '997' => 'شما اجازه ارسال از این پترن را ندارید.',
	  '998' => 'کارت ملی یا کارت بانکی شما تایید نشده است.',
	  '1001' => 'فرمت نام کاربری درست نمی باشد)حداقله ۵ کاراکتر، فقط حروف و اعداد).',
	  '1002' => 'گذرواژه خیلی ساده می باشد. باید حداقل 8 کاراکتر بوده و از نام کاربری و ایمیل و شماره موبایل خود در آن استفاده نکنید.',
	  '1003' => 'مشکل در ثبت، با پشتیبانی تماس بگیرید.',
	  '1004' => 'مشکل در ثبت، با پشتیبانی تماس بگیرید.',
	  '1005' => 'مشکل در ثبت، با پشتیبانی تماس بگیرید.',
	  '1006' => 'تاریخ ارسال پیام برای گذشته می باشد، لطفا تاریخ ارسال پیام را به درستی وارد نمایید.',
	  
	  '1000100' => 'شما اجازه ارسال بین الملل را از سامانه پیامک رنگینه دریافت نکرده اید. با پشتیبانی سامانه پیامک رنگینه تماس بگیرید.',
	  '1000101' => 'کلید دسترسی خالی است.',
	  '1000102' => 'درگاه بین الملل پاسخی نداد.',
	  '1000103' => 'تعداد مجاز استفاده از دمو سامانه پیامک رنگینه برای سایت شما به پایان رسیده است. شما می توانید با تهیه یک پنل از سامانه پیامک رنگینه به ارسال پیامک از این افزونه ادامه دهید.',
	  '1000104' => 'شماره دریافت کننده تامعتبر است.',
	  '1000105' => 'اشکال در اتصال به وب سرویس.',
	  '1000105' => 'اشکال در اتصال به وب سرویس.',
	  '1000107' => 'کاربر مجاز به ارسال به آمریکا نیست.',
	  '1000108' => 'کاربر مجاز به ارسال به کانادا نیست.',
	 );
	 return (isset($errorCodes[$error])) ? $errorCodes[$error] : 'اشکال تعریف نشده با کد :' . $error;
	}
}
