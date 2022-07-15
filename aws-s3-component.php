<?php

class s3Component extends Component
{
    /**
     * S3 API connection/credentials handler
     * @param {*} s3_key //Position of the current chunk in the array
     * @param {*} s3_secret //Size of the current chunk in bytes
     * @param {*} s3_region //Total size of the upload or sum of the all the chunks
     * @returns object 
     */
    public function s3Connection($s3_key, $s3_secret, $s3_region)
    {
        try {
            $s3 = S3Client::factory(
                array(
                    'credentials' => array(
                        'key' => $s3_key,
                        'secret' => $s3_secret
                    ),
                    'version' => 'latest',
                    'region'  => $s3_region
                )
            );
            return $s3;
        } catch (Exception $e) {
            die("Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * S3 Put object into a bucket
     * @param {*} s3 //Object to be stored
     * @param {*} s3_bucket //Bucket name
     * @param {*} s3_filename //Destination filename to store the object/file
     * @param {*} new_file_name //New filename, generated
     * @returns object 
     */
    public function putObjects($s3, $s3_bucket, $s3_filename,  $new_file_name) {
        try {
            $result = $s3->putObject(
                array(
                    'Bucket'=>$s3_bucket,
                    'Key' =>  $s3_filename,
                    'SourceFile' => $new_file_name
                )
            );
        } catch (S3Exception $e) {
            die('Error:' . $e->getMessage());
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    /**
     * Fetch and preview an image
     * @param {*} id //Database image id
     * @param {*} model //Database model to fetch the image from
     * @param {*} field_name //filter field to search for files/file types
     * @param {*} path //Path where the file is saved inside the bucket
     * @returns object 
     */
    public function s3ImagePreview($id, $model, $field_name='file_name', $path=null){

        $this->loadModel('Settings');
        $this->loadModel($model);
        $data = $this->$model->findById($id)->first();
        $settings = $this->Settings->find()->first();
        $response   = ['status' => false, 'url' => ''];
        if($settings):
            $s3_bucket = $settings['s3_bucket'];
            $s3_secret = $settings['s3_secret'];
            $s3_key = $settings['s3_key'];
            $s3_region = $settings['s3_region'];
            $s3 = $this->s3Connection($s3_key, $s3_secret, $s3_region);
            $filename = $data->$field_name;
            if($model == "Organizations"):
                $s3Source = $path.DS.$filename;
            else:
                $s3Source = $data->path.DS.$filename;
            endif;
            if ($s3):
                $command = $s3->getCommand('GetObject', array(
                    'Bucket'      => $s3_bucket,
                    'Key'         => $s3Source,
                    'ContentType' => 'image/png',
                    'ResponseContentDisposition' => 'attachment; filename="'.$filename.'"'
                ));
                $signedUrl = $s3->createPresignedRequest($command, "+6 days");
                $presignedUrl = $signedUrl->getUri();
                $response   = ['status' => true, 'filename'=>$filename, 'url' => stripslashes(strval($presignedUrl))];
            endif;
        endif;
        return $response;
        exit;
    }

    /**
     * Upload file/folder into a bucket
     * @param {*} S3Object //S3 Object to use to handle the connection/data
     * @param {*} dir //Local directory where the file is located
     * @param {*} S3_folder //Remote Directory to save the file
     * @param {*} S3_bucket //Bucket name
     * @param {*} folderPath //Full destination Path
     * @param {*} folderStatus //Set folder/files status as available once uploaded
     * @returns object 
     */
    public function s3Upload($S3Object, $dir, $S3_folder, $S3_bucket, $folderPath='', $folderStatus=true) {
        $dirfiles = new Folder($dir);
        $files = $dirfiles->find('.*');
        foreach ($files as $filename) {
            $S3_filename = $S3_folder.DS.$filename;
            $source_file_name =$dir.DS.$filename;
            $this->putObjects($S3Object, $S3_bucket, $S3_filename, $source_file_name);
        }
        if(file_exists($folderPath) && $folderPath != ''){
            $this->deleteAll($folderPath, $folderStatus);
        }
    }

    /**
     * Delete all files/directories inside a given path
     * @param {*} dir //Directory Full path to be removed
     * @param {*} remove //Removal protection override
     * @returns object 
     */
    public function deleteAll($dir, $remove = false) {
        $structure = glob(rtrim($dir, "/").'/*');
        if (is_array($structure)) {
            foreach($structure as $file) {
                if (is_dir($file))
                    $this->deleteAll($file,true);
                else if(is_file($file))
                    unlink($file);
            }
        }
        if($remove):
            rmdir($dir);
        endif;
    }

    /**
     * Check if file/folder exists
     * @param {*} dirPath //Full path+file to check if it exists
     * @returns object 
     */
    public function ifFileExists($dirPath){
        $structure = glob(rtrim($dirPath, "/").'/*');
        $count = 0;
        if (is_array($structure)):
            foreach($structure as $file):
                if (is_dir($file)):
                elseif(is_file($file)):
                    $count++;
                    break;
                endif;
            endforeach;
        endif;
        return $count;
    }
}
?>