<?php

namespace App\Http\Controllers;

use App\FacebookFeed;
use App\following;
use App\TwitterFeed;
use App\User;
use ErrorException;
use Illuminate\Http\Request;
use App\celebrities;
use App\Post;
use App\Http\Requests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Mockery\CountValidator\Exception;

class apiController extends Controller
{
    public $FACEBOOK_TAG = "Facebook";
    public $TWITTER_TAG = "Twitter";



    public function getUserFeeds($id){
        //get celebrities followed by user of id $id
        $celebs = DB::table('celebrities')
            ->join('following','following.celebId','=', 'celebrities.id')
            ->where('following.userId','=',$id)
            ->get();
        $posts = array();
        foreach($celebs as $celeb){
            //get the all FacebookFeeds of the celebrity
            $celebFbFeeds = FacebookFeed::where('celeb_id','=',$celeb->id)->get();
            foreach($celebFbFeeds as $feed){
                $celebName = $celeb->name;
                $platform = $this->FACEBOOK_TAG;
                $post = new Post($feed, $platform, $celebName);
                array_push($posts,$post);
            }

            //get the all FacebookFeeds of the celebrity
            $celebTwitterFeeds = TwitterFeed::where('celeb_id','=',$celeb->id)->get();
            foreach($celebTwitterFeeds as $feed){
                $celebName = $celeb->name;
                $platform = $this -> TWITTER_TAG;
                $post = new Post($feed, $platform, $celebName);
                array_push($posts,$post);
            }
        }
        //sort the posts by timestamp
        usort($posts, array($this, 'cmp'));
        return $posts;
    }

    /**
     * Comparator function, which compares the timestamps of two passed Posts
     */
    public static function cmp($post1, $post2)
    {
        return strcmp($post2->timestamp, $post1->timestamp);
    }


    public function getCelebs()
    {
        return celebrities::all();
    }

    /**
     * Requests and returns the facebook posts of a celebrity through facebook's API
     * $id is the facebook user_id of the celeb.
     */
    public function makeFbCall($id)
    {
        $appID = '311242662591971';
        $appSecret = 'de2e7436f9543e1d3c816e5485cea79b';
        //Create an access token using the APP ID and APP Secret.
        $accessToken = $appID . '|' . $appSecret;
        //Tie it all together to construct the URL
        $url = "https://graph.facebook.com/$id/posts?access_token=$accessToken&fields=picture,name,message,created_time,full_picture,story&limit=3";
        try {
            $result = file_get_contents($url);
            $decoded = json_decode($result, true);
            return $decoded;
        }catch (ErrorException $e){
            echo 'exe';
            return null;
        }
    }

    /**
     * Requests and returns the tweets of a certain celebrity through twitter's API
     * $twtId is the screen_name of this celeb.
     */
    public function makeTwitterCall($twtId)
    {
        //This is all you need to configure.
        $app_key = 'gTglSLTCCdqbo6QPxuWUNFSUC';
        $app_token = 'UyAPVkzDeknBueoxzIkidVXMc0Qs9XjRLGhZ8mmLcc5HR3ighU';
        //These are our constants.
        $api_base = 'https://api.twitter.com/';
        $bearer_token_creds = base64_encode($app_key . ':' . $app_token);
        //Get a bearer token.
        $opts = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Authorization: Basic ' . $bearer_token_creds . "\r\n" .
                    'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                'content' => 'grant_type=client_credentials'
            )
        );
        $context = stream_context_create($opts);
        $json = file_get_contents($api_base . 'oauth2/token', false, $context);
        $result = json_decode($json, true);
        if (!is_array($result) || !isset($result['token_type']) || !isset($result['access_token'])) {
            die("Something went wrong. This isn't a valid array: " . $json);
        }
        if ($result['token_type'] !== "bearer") {
            die("Invalid token type. Twitter says we need to make sure this is a bearer.");
        }
        //Set our bearer token. Now issued, this won't ever* change unless it's invalidated by a call to /oauth2/invalidate_token.
        //*probably - it's not documentated that it'll ever change.
        $bearer_token = $result['access_token'];
        //Try a twitter API request now.
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $bearer_token
            )
        );
        $context = stream_context_create($opts);
        $json = file_get_contents($api_base . '1.1/statuses/user_timeline.json?count=2&include_rts=false&exclude_replies=true&screen_name=' . $twtId, false, $context);
        $tweets = json_decode($json, true);
        //echo $json;
        //print_r($tweets[0]['entities']['media'][0]['media_url']);
        return $tweets;
    }


    public function saveFeedsToDatabase()
    {
        $celebs = celebrities::all();
        foreach ($celebs as $celeb) {
            $fbId = $celeb->fbId;
            if ($fbId !== '') {
                try {
                    $fbResult = $this->makeFbCall($fbId);

                    foreach ($fbResult['data'] as $data) {
                        if (FacebookFeed::where('feed_id', '=',$data['id'] )->exists()) {
                            // facebook feed previously saved
                            break;
                        }
                        $facebookFeed = new FacebookFeed();
                        $facebookFeed->feed_id = $data['id'];
                        $facebookFeed->celeb_id = $celeb->id;
                        if(array_key_exists('message', $data)){
                            $facebookFeed->text = $data['message'];
                        }
                        if(array_key_exists('full_picture', $data)){
                            $facebookFeed->image_url = $data['full_picture'];
                        }
                        $facebookFeed->created_at = $data['created_time'];
                        $facebookFeed->save();
                    }
                }catch (ErrorException $e){
                    echo "ex: ".$e;
                }
            }
            $twtId = $celeb->twtId;
            if ($twtId !== '') {
                $twtResult = $this->makeTwitterCall($twtId);
                foreach ($twtResult as $result) {
                    if (TwitterFeed::where('feed_id', '=',$result['id_str'] )->exists()) {
                        // twitter feed previously saved
                        break;
                    }
                    $twitterFeed = new TwitterFeed();
                    $twitterFeed ->feed_id = $result['id_str'];
                    if(array_key_exists('text',$result)){
                        $twitterFeed ->text = $result['text'];
                    }
                    if (array_key_exists('media', $result['entities'])) {
                        $twitterFeed ->image_url = ($twtResult['0']['entities']['media']['0']['media_url']);
                    }
                    $twitterFeed ->celeb_id = $celeb ->id;
                    $twitterFeed ->created_at = $result['created_at'];
                    $twitterFeed->save();
                }
            }
        }
    }

    public function testTwitter(){
        $twtResult = $this->makeTwitterCall('cristiano');//selenagomez
        //$fbResult = $this->makeFbCall("cristiano");
        //print_r($fbResult);
        print_r($twtResult['0']);
       // print_r($twtResult['0']['entities']['media']['0']['media_url']);
       /* $twtJson = json_decode($twtResult['data']);
        echo $twtJson;*/
    }

    public function testPost(Request $request){
        echo "name ".$request->input("name");
    }


    public function followCeleb(Request $request){
        $userId = $request->input("userId");
        $celebId = $request->input("celebId");

        if (following::where([['userId','=',$userId.''],['celebId','=',$celebId.'']])->exists()) {
            return;
        }

        $following = new following();
        $following ->userId = $userId;
        $following ->celebId = $celebId;

        $following ->save();

    }

    public function getWebFeeds($id){
        //get celebrities followed by user of id $id
        $celebs = DB::table('celebrities')
            ->join('following','following.celebId','=', 'celebrities.id')
            ->where('following.userId','=',$id)
            ->get();
        $posts = array();
        foreach($celebs as $celeb){
            //get the all FacebookFeeds of the celebrity
            $celebFbFeeds = FacebookFeed::where('celeb_id','=',$celeb->id)->get();
            foreach($celebFbFeeds as $feed){
                $celebName = $celeb->name;
                $platform = $this->FACEBOOK_TAG;
                $post = new Post($feed, $platform, $celebName);
                array_push($posts,$post);
            }

            //get the all FacebookFeeds of the celebrity
            $celebTwitterFeeds = TwitterFeed::where('celeb_id','=',$celeb->id)->get();
            foreach($celebTwitterFeeds as $feed){
                $celebName = $celeb->name;
                $platform = $this -> TWITTER_TAG;
                $post = new Post($feed, $platform, $celebName);
                array_push($posts,$post);
            }
        }
        //sort the posts by timestamp
        usort($posts, array($this, 'cmp'));
        return view('Your_View')->withPosts($posts);
    }
}
