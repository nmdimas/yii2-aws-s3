<?php
/**
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
namespace NmDimas\YII2AwsS3;

use Aws\S3\Enum\CannedAcl;
use Aws\S3\S3Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use Yii;

/**
 *
 * AmazonS3Manager handles resources to upload/uploaded to Amazon AWS
 *
 */
class AmazonS3Manager extends Component
{

    /**
     * @var string Amazon access key
     */
    public $key;
    /**
     * @var string Amazon secret access key
     */
    public $secret;
    /**
     * @var string Amazon Bucket
     */
    public $bucket;

    /**
     * @var bool
     */
    public $enableV4 = false;

    /**
     * @var string
     */
    public $region;

    /**
     * @var \Aws\S3\S3Client
     */
    private $_client;

    /**
     * @inheritdoc
     */
    public function init()
    {
        foreach (['key', 'secret', 'bucket'] as $attribute) {
            if ($this->$attribute === null) {
                throw new InvalidConfigException(strtr('"{class}::{attribute}" cannot be empty.', [
                    '{class}' => static::className(),
                    '{attribute}' => '$' . $attribute
                ]));
            }
        }
        parent::init();
    }

    /**
     * Basic method to saves a file
     * @param \yii\web\UploadedFile $file the file uploaded. The [[UploadedFile::$tempName]] will be used as the source
     * file.
     * @param string $name the name of the file
     * @param array $options extra options for the object to save on the bucket. For more information, please visit
     * [[http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.S3.S3Client.html#_putObject]]
     * @return \Guzzle\Service\Resource\Model
     */
    public function save($file, $name, $options = [])
    {
        $options = ArrayHelper::merge([
            'Bucket' => $this->bucket,
            'Key' => $name,
            'SourceFile' => $file->tempName,
            'ACL' => CannedAcl::PUBLIC_READ // default to ACL public read
        ], $options);

        $this->getClient()->putObject($options);
    }


    /**
     * @param string $filePath path where is file.
     * @param string $bucketPath  Dir where to save file
     * @param string $fileName  The name with which the file will be saved on bucket
     * @param string|null $oldFileName If isset old file that need deleted. Use if need update entity.
     * @param array $options
     */
    public function uploadFile($filePath, $bucketPath, $fileName, $oldFileName = null, $options = [])
    {
        if ($oldFileName) {
            $this->delete($bucketPath . $oldFileName);
        }

        $options = ArrayHelper::merge([
            'Bucket' => $this->bucket,
            'Key' => $bucketPath . $fileName,
            'SourceFile' => $filePath,
            'ACL' => CannedAcl::PUBLIC_READ // default to ACL public read
        ], $options);
        $this->getClient()->putObject($options);
    }

    /**
     * Removes a file
     * @param string $name the name of the file to remove
     * @return boolean
     */
    public function delete($name)
    {
        $result = $this->getClient()->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $name
        ]);

        return $result['DeleteMarker'];
    }

    /**
     * Checks whether a file exists or not. This method only works for public resources, private resources will throw
     * a 403 error exception.
     * @param string $name the name of the file
     * @return boolean
     */
    public function fileExists($name)
    {
        $http = new \Guzzle\Http\Client();
        try {
            $response = $http->get($this->getUrl($name))->send();
        } catch (ClientErrorResponseException $e) {
            return false;
        }

        return $response->isSuccessful();
    }

    /**
     * Returns the url of the file or empty string if the file does not exists.
     * @param string $name the key name of the file to access
     * @return string
     */
    public function getUrl($name)
    {
        return $this->getClient()->getObjectUrl($this->bucket, $name);
    }

    /**
     * Returns a S3Client instance
     * @return \Aws\S3\S3Client
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $settings = [
                'key' => $this->key,
                'secret' => $this->secret
            ];
            if ($this->region) {
                $settings['region'] = $this->region;
            }
            if ($this->enableV4) {
                $settings['signature'] = 'v4';
            }

            $this->_client = S3Client::factory($settings);
        }

        return $this->_client;
    }
}
