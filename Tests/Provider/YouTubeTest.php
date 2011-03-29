<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Tests\Provider;

use Sonata\MediaBundle\Tests\Entity\Media;

class YoutubeProviderTest extends \PHPUnit_Framework_TestCase
{

    public function getProvider()
    {
        $resizer = $this->getMock('Sonata\MediaBundle\Media\ResizerInterface', array('resize'));
        $resizer->expects($this->any())
            ->method('resize')
            ->will($this->returnValue(true));

        $adapter = $this->getMock('Gaufrette\Filesystem\Adapter');

        $file = $this->getMock('Gaufrette\Filesystem\File', array(), array($adapter));

        $filesystem = $this->getMock('Gaufrette\Filesystem\Filesystem', array('get'), array($adapter));
        $filesystem->expects($this->any())
            ->method('get')
            ->will($this->returnValue($file));

        $cdn = new \Sonata\MediaBundle\CDN\Server('/updoads/media');

        $provider = new \Sonata\MediaBundle\Provider\YouTubeProvider('file', $filesystem, $cdn);
        $provider->setResizer($resizer);
        
        return $provider;
    }

    public function testProvider()
    {

        $provider = $this->getProvider();

        $media = new Media;
        $media->setName('Nono le petit robot');
        $media->setProviderName('youtube');
        $media->setProviderReference('BDYAbAtaDzA');
        $media->setProviderMetadata(json_decode('{"provider_url": "http:\/\/www.youtube.com\/", "title": "Nono le petit robot", "html": "<object width=\"425\" height=\"344\"><param name=\"movie\" value=\"http:\/\/www.youtube.com\/v\/BDYAbAtaDzA?fs=1\"><\/param><param name=\"allowFullScreen\" value=\"true\"><\/param><param name=\"allowscriptaccess\" value=\"always\"><\/param><embed src=\"http:\/\/www.youtube.com\/v\/BDYAbAtaDzA?fs=1\" type=\"application\/x-shockwave-flash\" width=\"425\" height=\"344\" allowscriptaccess=\"always\" allowfullscreen=\"true\"><\/embed><\/object>", "author_name": "timan38", "height": 344, "thumbnail_width": 480, "width": 425, "version": "1.0", "author_url": "http:\/\/www.youtube.com\/user\/timan38", "provider_name": "YouTube", "thumbnail_url": "http:\/\/i3.ytimg.com\/vi\/BDYAbAtaDzA\/hqdefault.jpg", "type": "video", "thumbnail_height": 360}', true));
        $media->setId(10);

        $this->assertEquals('http://www.youtube.com/v/BDYAbAtaDzA', $provider->getAbsolutePath($media), '::getAbsolutePath() return the correct path - id = 1');

        $media->setId(1023457);
        $this->assertEquals('http://www.youtube.com/v/BDYAbAtaDzA', $provider->getAbsolutePath($media), '::getAbsolutePath() return the correct path - id = 1023456');

        $this->assertEquals('http://i3.ytimg.com/vi/BDYAbAtaDzA/hqdefault.jpg', $provider->getReferenceImage($media));

        $this->assertEquals('0011/24', $provider->generatePath($media));
        $this->assertEquals('/updoads/media/0011/24/thumb_1023457_big.jpg', $provider->generatePublicUrl($media, 'big'));
    }

    public function testThumbnail()
    {

        $provider = $this->getProvider();

        $media = new Media;
        $media->setProviderName('youtube');
        $media->setProviderReference('BDYAbAtaDzA');
        $media->setProviderMetadata(json_decode('{"provider_url": "http:\/\/www.youtube.com\/", "title": "Nono le petit robot", "html": "<object width=\"425\" height=\"344\"><param name=\"movie\" value=\"http:\/\/www.youtube.com\/v\/BDYAbAtaDzA?fs=1\"><\/param><param name=\"allowFullScreen\" value=\"true\"><\/param><param name=\"allowscriptaccess\" value=\"always\"><\/param><embed src=\"http:\/\/www.youtube.com\/v\/BDYAbAtaDzA?fs=1\" type=\"application\/x-shockwave-flash\" width=\"425\" height=\"344\" allowscriptaccess=\"always\" allowfullscreen=\"true\"><\/embed><\/object>", "author_name": "timan38", "height": 344, "thumbnail_width": 480, "width": 425, "version": "1.0", "author_url": "http:\/\/www.youtube.com\/user\/timan38", "provider_name": "YouTube", "thumbnail_url": "http:\/\/i3.ytimg.com\/vi\/BDYAbAtaDzA\/hqdefault.jpg", "type": "video", "thumbnail_height": 360}', true));

        $media->setId(1023457);

        $this->assertTrue($provider->requireThumbnails($media));

        $provider->addFormat('big', array('width' => 200, 'height' => 100, 'constraint' => true));

        $this->assertNotEmpty($provider->getFormats(), '::getFormats() return an array');

        $provider->generateThumbnails($media);

        $this->assertEquals('0011/24/thumb_1023457_big.jpg', $provider->generatePrivateUrl($media, 'big'));
    }

    public function testEvent()
    {
        $provider = $this->getProvider();

        $provider->addFormat('big', array('width' => 200, 'height' => 100, 'constraint' => true));

        $media = new Media;
        $media->setBinaryContent('BDYAbAtaDzA');
        $media->setId(1023456);

        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'Sonata\\MediaBundle\\Tests\\Provider\\FakeHttpWrapper');
        
        // pre persist the media
        $provider->prePersist($media);

        $this->assertEquals('Nono le petit robot', $media->getName(), '::getName() return the file name');
        $this->assertEquals('BDYAbAtaDzA', $media->getProviderReference(), '::getProviderReference() is set');

        // post persit the media
        $provider->postPersist($media);

        $provider->postRemove($media);

        stream_wrapper_restore('http');
    }
}