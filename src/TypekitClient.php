<?php

/*
 * This file is part of the Mvpasarel\Typekit package.
 *
 * (c) Madalin Pasarel <madalin.pasarel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webeau\Typekit;

use GuzzleHttp\Client;
use Webeau\Typekit\Exceptions\NoFontFoundException;
use Webeau\Typekit\Exceptions\NoKitFoundException;
use Webeau\Typekit\Exceptions\TypekitException;

class TypekitClient
{
    /**
     * API Client version
     *
     * @var string
     */
    private $version = '1.1';

    /**
     * Typekit API host
     *
     * @var string
     */
    private $host = 'https://typekit.com/api/v1/json/';

    /**
     * List of allowed domains
     *
     * @var array
     */
    private $domains;

    /**
     * Debug flag
     *
     * @var bool
     */
    private $debug;

    /**
     * Typekit API token https://typekit.com/account/tokens
     *
     * @var string
     */
    private $token;

    /**
     * Guzzle HTTP Client
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * Typekit API param https://typekit.com/docs/api/css_names
     *
     * @var bool
     */
    protected $segmentedCssNames;

    /**
     * Create a new Typekit Instance
     * @param $token
     * @param array $domains
     * @param bool $debug
     * @param bool $segmentedCssNames
     * @throws TypekitException
     */
    public function __construct($token, $domains = ['localhost'], $debug = false, $segmentedCssNames = false)
    {
        if (!$token) {
            throw new TypekitException('The Typekit API Token must be provided');
        }

        $this->token = $token;
        $this->debug = $debug;
        $this->domains = $domains;
        $this->client = new Client();

        $this->segmentedCssNames = $segmentedCssNames;
    }

    /**
     * Returns a json representation of the kits associated with this api token
     */
    public function getKits()
    {
        return $this->makeRequest('GET', $this->buildUrl('list'));
    }

    /**
     * Returns an existing kit of given id (including unpublished ones)
     *
     * @param $kitId
     * @return mixed
     * @throws NoKitFoundException
     */
    public function getKit($kitId)
    {
        $kit = $this->makeRequest('GET', $this->buildUrl('get', $kitId));

        if (isset($kit['errors'])) {

            throw new NoKitFoundException('Kit with id "' . $kitId . '" does not exist');
        }

        return $kit;
    }

    /**
     * Updates or creates a new kit.
     * @param null $kitId
     * @param string $name
     * @param array $domains
     * @param array $families
     *  - 'id' => family id (string)
     *  - (optional) 'variations' => comma separated variations (string)
     * @param bool $optimize
     * @return bool If successful returns true, else returns false
     * @throws TypekitException
     */
    private function modifyKit($kitId = null, $name = '', $domains = [], $families = [], $optimize = null)
    {
        $params = [];

        if (!$this->segmentedCssNames) {
            $params['segmented_css_names'] = false;
        }

        if ($name) {
            $params['name'] = $name;
        }
        $params['domains'] = implode(',', $this->domains);
        if (!empty($domains)) {
            $params['domains'] = implode(',', $domains);
        }

        if (!empty($families)) {
            foreach ($families as $idx => $family) {
                if (!isset($family['id'])) {

                    throw new TypekitException('The "id" key is required for families');
                }

                $params['families[' . urlencode($idx) . '][id]'] = $family['id'];

                if (isset($family['variations'])) {
                    $params['families[' . urlencode($idx) . '][variations]'] = $family['variations'];
                }
            }
        }

        if ($optimize !== null) {
            $params['optimize_performance'] = $optimize;
        }

        if (is_null($kitId)) {
            $url = $this->buildUrl('create');
        } else {
            $url = $this->buildUrl('update', $kitId);
        }

        return $this->makeRequest('POST', $url, $params);
    }

    /**
     * Completely replaces the existing value with the new value during POST request (Typekit spec)
     *
     * @param $kitId
     * @param string $name
     * @param array $domains
     * @param array $families
     * @param bool $optimize
     * @return string
     */
    public function updateKit($kitId, $name = '', $domains = [], $families = [], $optimize = null)
    {
        return $this->modifyKit($kitId, $name, $domains, $families, $optimize);
    }

    /**
     * Creates an existing kit
     * @param string $name
     * @param array $domains
     * @param array $families
     * @param bool $optimize
     * @return mixed
     */
    public function createKit($name = '', $domains = [], $families = [], $optimize = null)
    {
        $kitId = null;

        return $this->modifyKit($kitId, $name, $domains, $families, $optimize);
    }

    /**
     * Removes an existing kit.
     * @param $kitId
     * @return mixed
     */
    public function removeKit($kitId)
    {
        $url = $this->buildUrl('delete', $kitId);

        return $this->makeRequest('DELETE', $url);
    }

    /**
     * Publishes an existing kit.
     * @param $kitId
     * @return mixed
     */
    public function publishKit($kitId)
    {
        $url = $this->buildUrl('publish', $kitId);

        return $this->makeRequest('POST', $url);
    }

    /**
     * Change optimized performance flag
     *
     * @param $kitId
     * @param bool $optimize
     * @return bool
     * @throws TypekitException
     */
    public function optimizeKit($kitId, $optimize = true)
    {
        return $this->modifyKit($kitId, '', [], [], $optimize);
    }

    /**
     * Retrieves font information from Typekit.
     * Can use either font_slug or fontId. The font slug must
     * be a slug for it to work, so slugify your input before using it.
     * @param $font
     * @return mixed
     * @throws NoFontFoundException
     */
    public function getFontFamily($font)
    {
        $url = $this->buildUrl('families', '', $font);
        $font = $this->makeRequest('GET', $url);

        if (isset($font['errors'])) {

            throw new NoFontFoundException('Font "' . $font . '" does not exist');
        }

        return $font;
    }

    /**
     * Retrieves all variations of the font family.
     * If font does not exist, returns False
     * @param $font
     * @return mixed
     */
    public function getFontVariations($font)
    {
        $font = $this->getFontFamily($font);

        $variations = [];

        if (!empty($font['family']['variations'])) {
            foreach ($font['family']['variations'] as $var) {
                $variations[] = $var['fvd'];
            }
        }

        return $variations;
    }


    /**
     * Checks to see if a font exists in a kit.
     * If it does, returns True.
     * If the kit does not exist or the font does not exist, returns None.
     * Else, return False.
     * @param $kit
     * @param $font
     * @return bool
     * @throws NoFontFoundException
     */
    public function kitContainsFont($kit, $font)
    {
        $kitFonts = $this->getKitFonts($kit);

        if (count($kitFonts) === 0) {
            return false;
        }

        $font = $this->getFontFamily($font);

        return isset($font['family']['id']) && in_array($font['family']['id'], $kitFonts);
    }


    /**
     * Adds a font to a given kit.
     * Font is a string.
     * Variations is an optional tuple. Add only valid variations. If
     * variations is not given, adds all variations (default behavior).
     * If font exists in kit, returns without doing anything.
     * Else, adds font to kit, returns.
     * @param $kitId
     * @param $font
     * @param array $variations
     */
    public function kitAddFont($kitId, $font, $variations = [])
    {
        if ($this->kitContainsFont($kitId, $font)) {
            # Font already in kit
            return;
        }

        $newFontFamily = ['id' => $font];

        # add only the valid variations
        if (!empty($variations)) {
            $fontAvailVars = $this->getFontVariations($font);
            $newVars = [];
            foreach ($variations as $var) {
                if (!isset($fontAvailVars[$var])) {
                    $newVars[] = $var;
                }
            }

            if (count($newVars) > 0) {
                $newFontFamily['variations'] = implode(',', $newVars);
            }
        }

        $kit = $this->getKitValues($kitId);
        $kit[2][] = $newFontFamily;

        $this->updateKit($kitId, $kit[0], $kit[1], $kit[2]);
    }

    /**
     * Removes a font from a given kit.
     * Font is a string.
     * If font does not exist in kit, returns without doing anything.
     * Else, removes font to kit, returns.
     * @param $kitId
     * @param $font
     * @return void
     */
    public function kitRemoveFont($kitId, $font)
    {
        if (!$this->kitContainsFont($kitId, $font)) {
            # Font not in kit. Nothing to remove.
            return;
        }

        $kit = $this->getKitValues($kitId);
        $fontData = $this->getFontFamily($font);
        $fontId = $fontData['family']['id'];

        $families = [];
        foreach ($kit[2] as $idx => $family) {
            if ($fontId == $family['id']) {
                continue;
            }
            $families[$idx] = $family;
        }

        $this->updateKit($kitId, $kit[0], $kit[1], $families);
    }

    /**
     * Retrieves kit values in a list of format: [name, domains, families]
     * @param $kitId
     * @return array
     */
    public function getKitValues($kitId)
    {
        $kit = $this->getKit($kitId);
        $kit = $kit['kit'];
        $families = [];
        foreach ($kit['families'] as $f) {
            $family = [
                'id' => $f['id'],
                'variations' => implode(',', $f['variations']),
            ];
            $families[] = $family;
        }

        return [$kit['name'], $kit['domains'], $families];
    }

    /**
     * Retrieves a list of font ids in a given kit
     * Returns None if kit does not exist
     * Returns an empty list if no fonts in kit
     * @param $kitId
     * @return array
     */
    public function getKitFonts($kitId)
    {
        $kit = $this->getKit($kitId);
        $fonts = [];
        if (!empty($kit['kit']['families'])) {
            foreach ($kit['kit']['families'] as $family) {
                $fonts[] = $family['id'];
            }
        }

        return $fonts;
    }

    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * @param array $domains
     */
    public function setDomains($domains)
    {
        $this->domains = $domains;
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param $method
     * @param $url
     * @param array $params
     * @return mixed
     */
    private function makeRequest($method, $url, $params = [])
    {
        $userAgent = 'PHP Typekit API Wrapper v' . $this->version;
        $headers = [
            'User-Agent' => $userAgent,
        ];

        $form_params = $params;
        $options = compact('headers', 'form_params');

        if ($method === 'GET') {
            $response = $this->client->get($url, compact('headers'));
        } elseif ($method === 'POST') {
            $response = $this->client->post($url, $options);
        } elseif ($method === 'DELETE') {
            $response = $this->client->delete($url, $options);
        }

        return \GuzzleHttp\json_decode((string)$response->getBody(), true);
    }

    /**
     * @param $method
     * @param string $kitId
     * @param string $font
     * @return string
     */
    private function buildUrl($method, $kitId = '', $font = '')
    {
        $url = $this->host;

        if ($method === 'list' || $method === 'create') {
            $url .= 'kits';
        }

        if ($method === 'get' || $method === 'update' || $method === 'delete') {
            $url .= 'kits/' . urlencode($kitId);
        }

        if ($method === 'publish') {
            $url .= 'kits/' . urlencode($kitId) . '/publish';
        }

        if ($method === 'families') {
            $url .= 'families/' . urlencode($font);
        }

        $url .= '?token=' . $this->token;

        return $url;
    }
}
