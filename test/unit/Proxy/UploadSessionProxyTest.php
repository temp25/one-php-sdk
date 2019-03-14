<?php

namespace Test\Unit\Krizalys\Onedrive\Proxy;

use Psr\Http\Message\StreamInterface;
use Krizalys\Onedrive\Proxy\DriveItemProxy;
use Krizalys\Onedrive\Proxy\UploadSessionProxy;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Microsoft\Graph\Http\GraphResponse;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model\UploadSession;

class UploadSessionProxyTest extends \PHPUnit_Framework_TestCase
{
    public function testExpirationDateTimeShouldReturnExpectedValue()
    {
        $graph         = $this->createMock(Graph::class);
        $dateTime      = new \DateTime();
        $uploadSession = $this->createMock(UploadSession::class);
        $uploadSession->method('getExpirationDateTime')->willReturn($dateTime);
        $sut = new UploadSessionProxy($graph, $uploadSession, '');
        $this->assertSame($dateTime, $sut->expirationDateTime);
    }

    public function testNextExpectedRangesShouldReturnExpectedValue()
    {
        $graph         = $this->createMock(Graph::class);
        $uploadSession = $this->createMock(UploadSession::class);
        $uploadSession->method('getNextExpectedRanges')->willReturn(['0-1', '2-3']);
        $sut = new UploadSessionProxy($graph, $uploadSession, '');
        $this->assertInternalType('array', $sut->nextExpectedRanges);
        $this->assertSame(['0-1', '2-3'], $sut->nextExpectedRanges);
    }

    public function testUploadUrlShouldReturnExpectedValue()
    {
        $graph         = $this->createMock(Graph::class);
        $uploadSession = $this->createMock(UploadSession::class);
        $uploadSession->method('getUploadUrl')->willReturn('http://uplo.ad/url');
        $sut = new UploadSessionProxy($graph, $uploadSession, '');
        $this->assertInternalType('string', $sut->uploadUrl);
        $this->assertSame('http://uplo.ad/url', $sut->uploadUrl);
    }

    public function testCompleteShouldReturnExpectedValue()
    {
        $item = $this->createMock(DriveItem::class);
        $item->method('getId')->willReturn('123abc');

        $response = $this->createMock(GraphResponse::class);
        $response->method('getStatus')->willReturn('201');
        $response->method('getResponseAsObject')->willReturn($item);

        $request = $this->createMock(GraphRequest::class);
        $request->method('addHeaders')->willReturnSelf();
        $request->method('attachBody')->willReturnSelf();
        $request->method('execute')->willReturn($response);

        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willReturn($request);

        $uploadSession = $this->createMock(UploadSession::class);
        $content       = '';
        $sut           = new UploadSessionProxy($graph, $uploadSession, $content, []);
        $actual        = $sut->complete();
        $this->assertInstanceOf(DriveItemProxy::class, $actual);
        $this->assertSame('123abc', $actual->id);
    }
	
	public static function testCallback(array $values)
	{
		static $isInvoked = false;
		
		if(!$isInvoked) {
			$isInvoked = true;
			return $values[0];
		}
		
		return $values[1];
	}
	
	public function testCompleteWithTextContentShouldReturnExpectedValue()
    {
        $item = $this->createMock(DriveItem::class);
        $item->method('getId')->willReturn('test123');

        $response = $this->createMock(GraphResponse::class);
        $response
			->method('getStatus')
			->will($this->returnCallback(
                function() {
					return self::testCallback([202, 201]);
                }
            ));
        $response->method('getResponseAsObject')->willReturn($item);

        $request = $this->createMock(GraphRequest::class);
        $request->method('addHeaders')->willReturnSelf();
        $request->method('attachBody')->willReturnSelf();
        $request->method('execute')->willReturn($response);

        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willReturn($request);

        $uploadSession = $this->createMock(UploadSession::class);
		
		$content = "";
		for($i=1;$i<=20000;$i++) { //creates a 340KiB  file content
			$content .= "Sample text $i".PHP_EOL;
		}
		
		$stream = $this->createMock(StreamInterface::class);
		$stream->method('getSize')->willReturn(348894);
		$stream
			->method('eof')
			->will($this->returnCallback(
                function() {
					return self::testCallback([false, true]);
                }
            ));
		$options = [
			'range_size' => 348894,
			'type' => 'text/plain'
		];
        $sut           = new UploadSessionProxy($graph, $uploadSession, $content, $options);
        $actual        = $sut->complete();
        $this->assertInstanceOf(DriveItemProxy::class, $actual);
        $this->assertSame('test123', $actual->id);
    }
	
}
