<?php

namespace Ideato\FlickrApiBundle\Wrapper;

class FlickrApi
{
    protected $curl;
    protected $url;
    protected $user_id;
    protected $api_key;

    /**
     * @see http://www.flickr.com/services/api/
     *
     * @param \Ideato\FlickrApiBundle\Wrapper\Curl $curl
     * @param string $url
     * @param string $user_id
     * @param string $api_key
     */
    public function __construct(\Ideato\FlickrApiBundle\Wrapper\Curl $curl, $url, $user_id, $api_key)
    {
        if (empty($url) || empty($user_id) || empty($api_key))
        {
            throw new \InvalidArgumentException('Url, user_id and api_key are mandatory for using flickr api');
        }

        $this->curl = $curl;
        $this->url = $url;
        $this->user_id = $user_id;
        $this->api_key = $api_key;
    }

    /**
     * Calls the flickr.photosets.getList througt the Curl wrapper
     * Returns an array of XMLElement
     * 
     * @return SimpleXMLElement
     */
    public function getPhotoSets()
    {
        $results = $this->curl->get($this->buildBaseUrl('flickr.photosets.getList'));
        $xml = \simplexml_load_string($results);

        if (!$xml || count($xml->photosets->photoset) <= 0)
        {
            return array();
        }

        foreach ($xml->photosets->photoset as $photo_set)
        {
            $attributes = $photo_set->attributes();
            $photo_set->addChild('preview', $this->getPhotoSetPreview((string)$attributes['id']));
            $photo_set->addChild('id', $attributes['id']);
        }

        return $xml->photosets->photoset;
    }

    /**
     * Calls flickr.photosets.getPhotos througt the Curl wrapper to get the first picture of the set
     *
     * @return string the url of the image
     */
    public function getPhotoSetPreview($photoset_id)
    {
        $results = $this->curl->get($this->buildPhotoSetPreviewUrl('flickr.photosets.getPhotos', $photoset_id));
        $xml = \simplexml_load_string($results);
        if ($xml && count($xml->photoset->photo) > 0)
        {
            return $this->buildPhotoUrl($xml->photoset->photo->attributes());
        }

        return null;
    }

    /**
     * Checks whether the given xml has the "rsp" element with the "stat" attribute set to "ok"
     *
     * @param \DOMDocument $doc
     * @return boolean
     */
    protected function isValidResponse(\DOMDocument $doc)
    {
        return 'ok' == $doc->getElementsByTagName('rsp')->item(0)->getAttributeNode('stat')->value;
    }

    /**
     * Calls the given url and creates and returns a dom document based on the recieved xml
     *
     * @param string $url
     * @return \DOMDocument
     *
     * @throw Exception if the respose is not valid or there are errors qith the curl wrapper call
     */
    protected function loadDomDocument($url)
    {
        $dom = new \DOMDocument();
        $dom->loadXML($this->curl->get($url));
        if (!$this->isValidResponse($dom) || $this->curl->hasError())
        {
            throw new \Exception('Error callling '.$url);
        }

        return $dom;
    }

    /**
     * Calls flickr.photosets.getInfo througt the Curl wrapper to get the informations of the photo set
     * Calls flickr.photosets.getPhotos througt the Curl wrapper to get all the pictures of the photo set
     *
     * @return SimpleXMLElement
     */
    public function getPhotoSet($photoset_id)
    {
        try
        {
            $info_set = $this->loadDomDocument($this->buildPhotoSetUrl('flickr.photosets.getInfo', $photoset_id));
            $photos = $this->loadDomDocument($this->buildPhotoSetUrl('flickr.photosets.getPhotos', $photoset_id, '&extras=path_alias,url_sq,url_t,url_s,url_m,url_o'));

            $photos_element = $info_set->createElement('photos');
            foreach ($photos->getElementsByTagName('photo') as $photo)
            {
                $photos_element->appendChild($info_set->importNode($photo));
            }
            $info_set->getElementsByTagName('photoset')->item(0)->appendChild($photos_element);

            return \simplexml_import_dom($info_set->getElementsByTagName('photoset')->item(0));

        }
        catch (\Exception $e)
        {
        }

        return null;
    }

    /**
     * Returns the url of a single flickr picture
     * format: http://farm{farm-id}.static.flickr.com/{server-id}/{id}_{secret}_[mstzb].jpg
     *
     * @param mixed $attributes
     * @param string $size
     * @return string
     */
    protected function buildPhotoUrl($attributes, $size = 'm')
    {
        return 'http://farm'.$attributes['farm'].'.static.flickr.com/'.$attributes['server'].'/'.$attributes['id'].'_'.$attributes['secret'].'_'.$size.'.jpg';
    }

    /**
     * Builds the url for retrieving the data about the first image of a photo set
     *
     * @param string $method
     * @param string $photoset_id
     * @return string
     */
    protected function buildPhotoSetPreviewUrl($method, $photoset_id)
    {
        return $this->buildBaseUrl($method).'&photoset_id='.$photoset_id.'&per_page=1&page=1';
    }

    /**
     * Builds the url for retrieving the data about all the images of a photo set
     *
     * @param string $method
     * @param string $photoset_id
     * @param string $extra_parameters
     * @return string
     */
    protected function buildPhotoSetUrl($method, $photoset_id, $extra_parameters = '')
    {
        return $this->buildBaseUrl($method).'&photoset_id='.$photoset_id.$extra_parameters;
    }

    /**
     * Builds the basic url for any method of the flickr api
     *
     * @param string $method
     * @return string
     */
    protected function buildBaseUrl($method)
    {
        return $this->url.'method='.$method.'&api_key='.$this->api_key.'&user_id='.$this->user_id;
    }

}
