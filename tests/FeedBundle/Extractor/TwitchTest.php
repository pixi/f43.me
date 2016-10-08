<?php

namespace Tests\FeedBundle\Extractor;

use Api43\FeedBundle\Extractor\Twitch;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

class TwitchTest extends \PHPUnit_Framework_TestCase
{
    public function dataMatch()
    {
        return array(
            array('https://www.twitch.tv/tomfawkes/v/91819468', true),
            array('https://twitch.tv/tomfawkes/v/91819468', true),
            array('http://www.twitch.tv/tomfawkes/v/91819468', true),
            array('https://www.twitch.tv/directory/game/Clustertruck', false),
            array('http://google.com', false),
            array('http://user@:80', false),
        );
    }

    /**
     * @dataProvider dataMatch
     */
    public function testMatch($url, $expected)
    {
        $twitch = new Twitch('apikey');
        $this->assertEquals($expected, $twitch->match($url));
    }

    public function testContent()
    {
        $client = new Client();

        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode(['title' => 'hihi', 'description' => 'hoho', 'preview' => 'http://0.0.0.0/image.jpg']))),
            new Response(200, [], Stream::factory(json_encode([]))),
            new Response(400, [], Stream::factory('oops')),
        ]);

        $client->getEmitter()->attach($mock);

        $twitch = new Twitch('apikey');
        $twitch->setClient($client);

        $logHandler = new TestHandler();
        $logger = new Logger('test', array($logHandler));
        $twitch->setLogger($logger);

        // first test fail because we didn't match an url, so TwitchId isn't defined
        $this->assertEmpty($twitch->getContent());

        $twitch->match('https://www.twitch.tv/tomfawkes/v/91819468');

        // consecutive calls
        $this->assertEquals('<div><h2>hihi</h2><p>hoho</p><p><img src="http://0.0.0.0/image.jpg"></p><iframe src="https://player.twitch.tv/?video=v91819468" frameborder="0" scrolling="no" height="378" width="620"></iframe></div>', $twitch->getContent());
        // this one will got an empty array
        $this->assertEmpty($twitch->getContent());
        // this one will catch an exception
        $this->assertEmpty($twitch->getContent());

        $this->assertTrue($logHandler->hasWarning('Twitch extract failed for: 91819468'), 'Warning message matched');
    }
}
