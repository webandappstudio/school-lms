<?php

class ZoomComponent extends Component
{
    
    public $components = ['CurlInit', 'CommonFunctions'];

    /**
     * Generate access token the configured accounts
     * @param {*} $apiKey
     * @param {*} $apiSecret
     * @return string
     */
    public function getAccessToken($apiKey, $apiSecret)
    {
        $time = new Time('+1 day');
        $secret = $apiSecret;
        $payload = [
            "iss" => $apiKey,
            "exp" => $time->format("U")
        ];
        $token = JWT::encode($payload, $secret, 'HS256');
        return $token;
    }

    /**
     * Fetch meeting Participants
     * @param {*} url //Endpoint url
     * @param {*} token //Authentication Token
     * @param {*} meetingId //Id of the meeting to be retrieved
     * @param {*} savezoomMeetingID //Generated id to be saved
     * @returns null 
     */
    public function getMeetingParticipiants($url, $token, $meetingId, $savezoomMeetingID){
        $participantsData = $this->CurlInit->curl_auth_get($url, $token);
        if(isset($participantsData['data']) && !empty($participantsData['data'])):
            if(isset($participantsData['data']->participants) && !empty($participantsData['data']->participants)):
                $participants = $participantsData['data']->participants;
                $this->saveZoomParticipants($participants, $savezoomMeetingID);
                if($participantsData['data']->next_page_token!=''):
                    $url = 'https://api.zoom.us/v2/report/meetings/'.$meetingId.'/participants?page_size=300&next_page_token='.$participantsData['data']->next_page_token;
                    $this->getMeetingParticipiants($url, $token, $meetingId, $savezoomMeetingID);
                endif;
            endif;
        endif;
    }
}

?>