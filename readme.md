# AWS plugin for KISSCMS

Simple CRUD methods for [AWS](http://aws.amazon.com/) for [KISSCMS](http://kisscms.com/)

## Dependencies

* [AWS PHP SDK v2.5.2](https://github.com/aws/aws-sdk-php/releases/tag/2.5.2)
* [KISSCMS](https://github.com/makesites/kisscms) >= 2.1.0

## Install

Add the plugin in your plugins folder manually or as a submodule, for example:
```
git submodule install git://github.com/kisscms/aws.git ./app/plugins/aws/
```
In your ```env.json``` you'll need to add where your root SDK folder. The SDK path will be used to include the AWS PHP SDK, which should live in this path:
```
SDK. "aws/[VERSION]/sdk.class.php"
```


## Usage

Create models using the ```AWS_SimpleDB```
```
class MyModel extends AWS_SimpleDB {
}
```

Then use like any other KISSCMS module:
```
$data = new MyModel();

$data->set(...);
$data->create();
```
or
```
$data = new MyModel( id );

$data->set(...);
$data->update();
```

## Methods

Currently supporting the basic CRUD methods: ```create, read, update, delete```


## Options

These are the options added in the site's configuration

### Simple DB

* **simpleDB_host**: The host of the SimpleDB table(s) (default: sdb.us-west-1.amazonaws.com)
* **simpleDB_timestamps**: If enabled inserts ```created``` & ```updated``` flags for every item (default: true)
* **simpleDB_soft_delete**: A boolean that if selected hides the items instead of deleting (default: false)

### S3

* **s3_region**: The region of the S3 bucket(s) (default: s3-us-west-1.amazonaws.com)


## Credits

Created by Makis Tracend ( [@tracend](http://github.com/tracend) )

Distributed through [Makesites.org](http://makesites.org/)

Released under the [MIT license](http://makesites.org/licenses/MIT)
