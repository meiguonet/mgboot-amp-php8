<?php

namespace mgboot;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Options;
use Amp\Http\Server\Request as AmpRequest;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response as AmpResponse;
use Amp\Loop;
use Amp\Socket\Server;
use mgboot\http\server\Request;
use mgboot\http\server\Response;
use mgboot\logger\NoopLogger;
use mgboot\util\FileUtils;

final class ExampleServer
{
    public function start(): void
    {
        MgBoot::inAmpMode(true);

        Loop::run(function () {
            $host = AppConf::getString("http-server.host");

            if (empty($host)) {
                $host = '0.0.0.0';
            }

            $port = AppConf::getInt('http-server.port');

            if ($port < 1) {
                $port = 80;
            }

            $maxBodySize = AppConf::getDataSize('http-server.max-body-size');

            if ($maxBodySize < 8 * 1024) {
                $maxBodySize = 1024 * 1024 * 64;
            }

            $sockets = [
                Server::listen("$host:$port")
            ];

            $opts = new Options();
            $opts = $opts->withBodySizeLimit($maxBodySize);

            if (MgBoot::isGzipOutputEnabled()) {
                $opts = $opts->withCompression();
            }

            $handler = new CallableRequestHandler(function (AmpRequest $req) {
                $request = Request::create($req);
                $response = Response::create($request, new AmpResponse());
                MgBoot::handleRequest($request, $response);
                return $response->sendByAmpResponse();
            });

            $server = new HttpServer($sockets, $handler, NoopLogger::create(), $opts);

            Loop::onSignal(SIGINT, function (string $watcherId) use ($server) {
                Loop::cancel($watcherId);
                yield $server->stop();
                $this->removePidFile();
            });

            yield $server->start();

            if (AppConf::getEnv() === 'dev') {
                return;
            }

            $pidFinder = AppConf::getString('http-server.pid-finder');

            if (empty($pidFinder)) {
                return;
            }

            $pid = -1;
            $ts1 = time();

            while (true) {
                if (time() - $ts1 > 5) {
                    break;
                }

                $n1 = $this->getPid($pidFinder);

                if ($n1 > 0) {
                    $pid = $n1;
                    break;
                }

                usleep(100 * 1000);
            }

            $logger = MgBoot::getRuntimeLogger();

            if ($pid > 0) {
                $logger->info("success to start http server, listen at $host:$port");
                $this->writePidFile($pid);
            } else {
                $logger->error('fail to start http server');
            }
        });
    }

    private function getPid(string $pidFinder): int
    {
        $code = -1;
        $lines = [];
        exec($pidFinder, $lines, $code);

        if ($code !== 0) {
            return -1;
        }

        if (!is_array($lines) || empty($lines)) {
            return -1;
        }

        $line = collect($lines)->map(fn($it) => trim($it))->filter(fn($it) => is_string($it) && $it !== '')->first();

        if (!is_string($line) || $line === '') {
            return -1;
        }

        $pid = preg_replace('/[^0-9]+/', '', trim($line));
        return Cast::toInt($pid);
    }

    private function writePidFile(int $pid): void
    {
        $pidFile = AppConf::getString('http-server.pid-file');

        if (empty($pidFile)) {
            return;
        }

        file_put_contents(FileUtils::getRealpath($pidFile), "$pid");
    }

    private function removePidFile(): void
    {
        $pidFile = AppConf::getString('http-server.pid-file');

        if (empty($pidFile)) {
            return;
        }

        $pidFile = FileUtils::getRealpath($pidFile);

        if (!is_file($pidFile)) {
            return;
        }

        unlink($pidFile);
    }
}
