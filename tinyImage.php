<?php

//load dependency
include ('upload.php');

//alias for DIRECTORY_SEPARATOR
if(!defined('DS'));
    define('DS', DIRECTORY_SEPARATOR);
    
/**
 * Upload and Image manipulation class 
 * Based on the upload class of Colin Verot (http://www.verot.net).
 * Respect the KISS moto
 * @author Arnaud LEMAIRE <a.lemaire@quantis.fr>
 */

class tinyImage {


    protected $imageRootPath;
    protected $imageRootURI;
    private $height = 250;
    private $width = 250;
    private $resize = false;
    private $crop = false;
    private $fill = false;

    /**
     * @return void
     */
    public function __construct() {

        /*
         * CONFIG :
         * Indicate root path to move uploaded pictures and the associate URL.
         */
        
       $this->imageRootPath = DS . 'upload' . DS . 'images' . DS;
       $this->imageRootURI = '/upload/images/';

       
        //initialization : make root directory
        if (!file_exists($this->imageRootPath)) {
            mkdir($this->imageRootPath, 0770, true);
        }
        
        //array of default value :
        $this->defaultAttributes = array(
            'height' => $this->height,
            'width' => $this->width,
            'resize' => $this->resize,
            'crop' => $this->crop,
            'fill' => $this->fill
        );
        
        return $this;
    }


    /**
     * Add an image into tinyImage repository
     * @param string $pathToFile image's path
     * @param bool $keep_original_image false : delete image after process
     * @return string image's identifier
     * @throws Exception
     */
    public function add($pathToFile, $keep_original_image = true) {

        $imageClass = new externalImageUpload($pathToFile);

        $imageClass->file_new_name_body = uniqueId();

        $imageClass->process($this->getImageDirectory($imageClass->file_new_name_body));

        if (!$imageClass->processed) {
            throw new Exception('Error occured in upload process');
        }

        if (!$keep_original_image)
            $imageClass->clean();

        return $imageClass->file_dst_name_body . '|' . $imageClass->file_dst_name_ext;
    }

    /**
     * Add an uploaded image to the tinyImage repository
     * @param array $pathToFile $_FILE['image_name']
     * @return string image's id
     */
    public function upload($pathToFile) {

        return $this->add($pathToFile, false);
    }

    /**
     * Get the original image file
     * @param type $imageOriginalName
     * @return string URI to the original image's file
     * @throws Exception
     */
    public function getOriginal($imageOriginalName) {

        $originalFileName = str_replace('|', '.', $imageOriginalName);
        
        $imageFilePath = $this->getImageDirectory($originalFileName);
        
        if (!file_exists($imageFilePath . $originalFileName)) {
            throw new Exception('Original image not found');
        }
        
        return $this->getURI($originalFileName);
       
    }
    
    /**
     * Delete image in all of these sizes
     * @param string $imageOriginalName image's id
     * @return true
     */
    public function delete($imageOriginalName){
        
        $originalFileName = explode('|', $imageOriginalName);
        
        $imageFilePath = $this->getImageDirectory($originalFileName[0]);
        
        $images = glob($imageFilePath.$originalFileName[0].'*');
        
        foreach($images as $image){
            unlink($image);
        }
        
        return true;
        
    }
    
    /**
     * Get an image at a specified size
     * @param string $imageOriginalName image's id
     * @param int $size_x max width of image
     * @param int $size_y max heigt of image
     * @param string|array $options strategy to resize image
     * @return string image's URI
     * @throws Exception
     */
    public function get($imageOriginalName, $size_x = 0, $size_y = 0, $options = array()) {

        $this->setSize($size_x, $size_y, $options);

        $imageName = $this->getImageFileName($imageOriginalName);

        $imageFilePath = $this->getImageDirectory($imageName);

        //image already exist
        if (file_exists($imageFilePath . $imageName)) {
            $this->reset();
            return $this->getURI($imageName);
        }

        //image not exist
        $originalFileName = str_replace('|', '.', $imageOriginalName);

        if (!file_exists($imageFilePath . $originalFileName)) {
            throw new Exception('Original image not found');
        }

        $imageClass = new externalImageUpload($imageFilePath . $originalFileName);

        $imageClass->file_new_name_body = $this->getImageFileName($imageOriginalName, false);

        //if resize
        if ($this->resize) {

            $imageClass->image_resize = true;
            $imageClass->image_ratio = true;

            if ($this->width == 'auto' && $this->height == 'auto') {
                
            } elseif ($this->width == 'auto') {
                $imageClass->image_y = $this->height;
                $imageClass->image_ratio_x = true;
            } elseif ($this->height == 'auto') {
                $imageClass->image_x = $this->width;
                $imageClass->image_ratio_y = true;
            } else {
                $imageClass->image_x = $this->width;
                $imageClass->image_y = $this->height;
            }

            if (!empty($this->crop))
                $imageClass->image_ratio_crop = $this->crop;

            if (!empty($this->fill))
                $imageClass->image_ratio_fill = $this->fill;
        }

        $imageClass->Process($imageFilePath);

        if (!$imageClass->processed) {
            throw new Exception('Error occured in upload process');
        }
        
        //reset to default property
        $this->reset();
        
        return $this->getURI($imageClass->file_dst_name);
    }

     /**
     * 
     * @param string $imageName image's id
     * @return string
     */
    protected function getImageDirectory($imageName) {

        $subDirectory = mb_substr($imageName, 0, 2);

        $path = $this->imageRootPath . $subDirectory . DS;

        if (!file_exists($path)) {
            mkdir($path, 0770, true);
        }

        return $path;
    }
    
    protected function setSize($size_x, $size_y, $options = 'auto') {

        //check arguments' integrity
        $check_args = function($arg) {
                    return (is_int($arg) || $arg == 'auto' );
                };

        if (!$check_args($size_x) || !$check_args($size_y))
            throw new Exception('Arguments are invalids');


        //set size
        $this->resize = true;
        $this->width = (!$size_x) ? $this->width : $size_x;
        $this->height = (!$size_y) ? $this->height : $size_y;

        //options is a string
        if (!is_array($options))
            $options = array($options);

        foreach ($options as $option => $value) {

            //options have options ;-)
            if (is_int($option)) {
                $option = $value;
                $value = true;
            }

            if (!in_array($option, array('crop', 'fill')))
                throw new Exception('value\'s options argument is invalid, correct value is "crop" or "fill" ');

            //set options
            $this->$option = $value;
        }
    }

    protected function getURI($imageName) {

        $subPath = substr($imageName, 0, 2);

        $enicImageURI = $this->imageRootURI . $subPath . '/';

        return $enicImageURI . $imageName;
    }

    protected function getImageFileName($image_name_body, $ext = true) {

        $image_file = explode('|', $image_name_body);

        $image_name = $image_file[0];

        if (!empty($this->height))
            $image_name .= $this->height;

        if (!empty($this->width))
            $image_name .= $this->width;

        if (!empty($this->crop))
            $image_name .= ($this->crop !== true) ? $this->crop : 'crop';

        if (!empty($this->fill))
            $image_name .= ($this->fill !== true) ? $this->fill : 'fill';

        if ($ext)
            $image_name .= '.' . $image_file[1];

        return $image_name;
    }
    
    protected function reset(){
        
        //reset to default value
        foreach($this->defaultAttributes as $attr => $value){
            $this->$attr = $value;
        }
        
    }

}


?>
