<?php
namespace App;
use App\Http\Controllers\apiController;
use DateTime;

	class Post{
		public $platform;
		public $id;
		public $celebName;
		public $text;
		public $imageUrl;
		public $videoUrl;
		public $date;
		public $timestamp;


		public function __construct($feed, $platform, $celebName)
		{
			$this->celebName = $celebName;
			$this->platform = $platform;
			$this->id = $feed->feed_id;
			$this->text = $feed->text;
			$this->imageUrl = $feed->image_url;
			$this->videoUrl = $feed->videoUrl;
			$this->date= $feed->created_at;

			$dateFormat = 'd M Y H:i:s';
			$timestamp = strtotime($feed->created_at);
			$dateAfterTimeStampConversion = date($dateFormat, $timestamp);
			$this -> date = $dateAfterTimeStampConversion;
			$this->timestamp = $timestamp;
		}

	}

?>