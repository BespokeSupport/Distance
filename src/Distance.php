<?php

namespace BespokeSupport\Distance;

/**
 * Class Distance.
 */
class Distance
{
    /**
     * @var null
     */
    private $apiKey;
    /**
     * @var array
     */
    private $destinations = [];
    /**
     * @var array
     */
    private $errors = [];
    /**
     * @var string
     */
    protected $googleUrl = 'https://maps.googleapis.com/maps/api/distancematrix/';
    /**
     * @var
     */
    private $options;
    /**
     * @var array
     */
    private $origins = [];
    /**
     * @var string
     */
    private $returnType;

    /**
     * @param null   $apiKey
     * @param string $dataFormat
     * @param string $returnType
     *
     * @throws \Exception
     */
    public function __construct($apiKey = null, $dataFormat = 'both', $returnType = 'json')
    {
        if (!$apiKey) {
            throw new \Exception('You must specify an API key');
        } else {
            $this->apiKey = $apiKey;
        }
        $this->returnType = $returnType;
        $this->dataFormat = $dataFormat;
    }

    /**
     * @param array $options
     */
    public function changeOptions($options = [])
    {
        if (count($options)) {
            $this->options = $options;
        }
    }

    /**
     * @param null $from
     * @param null $to
     *
     * @return bool
     */
    public function validateInput($from = null, $to = null)
    {
        if ($to && $from && count($to) > 0 && count($from) > 0) {
            $this->destinations = $to;
            $this->origins = $from;

            return true;
        } elseif (count($this->destinations) < 1 && count($this->origins) < 1) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function prepareUrl()
    {
        $this->options['destinations'] = implode('|', $this->destinations);
        $this->options['origins'] = implode('|', $this->origins);

        $optionsString = http_build_query($this->options);

        $url = "{$this->googleUrl}{$this->returnType}?{$optionsString}&key={$this->apiKey}";

        return $url;
    }

    /**
     * @param $response
     *
     * @throws \Exception
     *
     * @return \StdClass|bool
     */
    public function validateResponse($response)
    {
        $hasError = false;

        if ($response->status === 'OVER_QUERY_LIMIT') {
            throw new \Exception($response->error_message);
        }

        if ($response->status === 'REQUEST_DENIED') {
            throw new \Exception($response->error_message);
        }

        if ($response->status === 'INVALID_REQUEST') {
            $hasError = true;
            $this->errors[] = 'Request was not valid';
        }

        foreach ($response->destination_addresses as $index => $address) {
            if ($address === '') {
                $hasError = true;
                $this->errors[] = "Destination address '{$this->destinations[$index]}' was invalid";
            }
        }
        foreach ($response->origin_addresses as $index => $address) {
            if ($address === '') {
                $hasError = true;
                $this->errors[] = "Origin address '{$this->origins[$index]}' was invalid";
            }
        }

        if ($hasError) {
            return false;
        }

        return $response;
    }

    /**
     * @param null $from
     * @param null $to
     *
     * @throws \Exception
     *
     * @return bool|Route
     */
    public function getResponse($from = null, $to = null)
    {
        if (!$this->validateInput($from, $to)) {
            return false;
        }

        $url = $this->prepareUrl();

        $response = $this->sendRequest($url);

        $response = $this->validateResponse($response);
        if (!$response) {
            return false;
        }

        $distance = 0;
        $duration = 0;
        foreach ($response->rows as $index => $element) {
            foreach ($element->elements as $innerIndex => $result) {
                $distance += $result->distance->value;
                $duration += $result->duration->value;
            }
        }

        $route = new Route();
        $route->totalDistanceMetres = $distance;
        $route->totalTimeSeconds = $duration;

        return $route;
    }

    /**
     * @param $url
     *
     * @return bool|mixed
     */
    public function sendRequest($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
        ]);

        $results = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return false;
        }

        return json_decode($results);
    }
}
