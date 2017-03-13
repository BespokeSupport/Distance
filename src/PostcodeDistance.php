<?php

namespace BespokeSupport\Distance;

use BespokeSupport\DatabaseWrapper\DatabaseWrapperInterface;
use BespokeSupport\Location\Postcode;

/**
 * Class PostcodeDistance.
 */
class PostcodeDistance
{
    /**
     * @var
     */
    private $apiKey;
    /**
     * @var DatabaseWrapperInterface
     */
    protected $database;
    /**
     * @var
     */
    protected $exceptionOnError = false;
    /**
     * @var string
     */
    public $source = 'cache';

    /**
     * @param $apiKey
     * @param DatabaseWrapperInterface|null $database
     */
    public function __construct($apiKey, DatabaseWrapperInterface $database = null)
    {
        $this->database = $database;
        $this->apiKey = $apiKey;
    }

    public function setExceptionOnError()
    {
        $this->exceptionOnError = true;
    }

    /**
     * @param $fromPostcode
     * @param $toPostcode
     *
     * @return \stdClass
     */
    public function getCache($fromPostcode, $toPostcode)
    {
        $sql = <<<'SQL'
            SELECT * FROM postcodeDistance
            WHERE
            (fromPostcode = :fromPostcode AND toPostcode = :toPostcode)
            OR
            (toPostcode = :toPostcode AND fromPostcode = :fromPostcode)
             ORDER BY created DESC
            LIMIT 1
SQL;
        $row = $this->database->sqlFetchOne($sql, [
            'fromPostcode' => $fromPostcode,
            'toPostcode'   => $toPostcode,
        ]);

        return $row;
    }

    public function getDistance($to, $from)
    {
        $this->source = 'cache';

        if (is_string($to)) {
            $to = [$to];
        }

        if (is_string($from)) {
            $from = [$from];
        }

        $toArray = [];
        foreach ($to as $postcode) {
            $validated = new Postcode($postcode);
            if (!$validated->getPostcode()) {
                continue;
            }
            $toArray[] = $validated->getPostcode();
        }

        $fromArray = [];
        foreach ($from as $postcode) {
            $validated = new Postcode($postcode);
            if (!$validated->getPostcode()) {
                continue;
            }
            $fromArray[] = $validated->getPostcode();
        }

        if (!count($toArray) || !count($fromArray)) {
            return;
        }

        if ($this->database && count($fromArray) == 1 && count($toArray) == 1) {
            $row = $this->getCache($fromArray[0], $toArray[0]);
            if ($row) {
                $route = new Route();
                $route->totalDistanceMetres = $row->distanceRoad;
                $route->totalTimeSeconds = $row->durationSeconds;

                return $route;
            }
        }

        $distanceObj = new Distance($this->apiKey, 'raw');

        $this->source = 'api';

        $response = $distanceObj->getResponse($fromArray, $toArray);
        if (!$response) {
            return false;
        }

        if ($this->database) {
            $this->database->insert('postcodeDistance', [
                'fromPostcode'    => $fromArray[0],
                'toPostcode'      => $toArray[0],
                'distanceRoad'    => $response->totalDistanceMetres,
                'durationSeconds' => $response->totalTimeSeconds,
            ]);
        }

        return $response;
    }
}
