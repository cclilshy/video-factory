<?php declare(strict_types=1);

namespace Cclilshy\VideoFactory;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\FrameRate;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

/**
 * Class Factory 视频压缩工厂
 */
class Factory
{
    /**
     * 递归深度
     * @var int $depth
     */
    private int $depth = 0;

    /**
     * 压缩视频
     * @param string     $input 视频文件路径
     * @param float|null $rate  目标速率
     * @return string|false
     */
    private function compressTheVideo(string $input, float|null $rate = 131072): string|false
    {
        $outputFile = '/tmp/' . md5($input) . '.mp4';
        $ffmpeg     = FFMpeg::create();
        $video      = $ffmpeg->open($input);

        // 获取文件大小
        $fileSize = filesize($input);

        // 获取视频比特率
        $bitRate = $video->getStreams()->videos()->first()->get('bit_rate');

        // 获取帧率
        $frameRate = intval($video->getStreams()->videos()->first()->get('r_frame_rate'));

        // 获取分辨率
        $dimensions = $video->getStreams()->videos()->first()->getDimensions();
        $width      = $dimensions->getWidth();
        $height     = $dimensions->getHeight();

        // 获取时长
        $duration = floor($video->getStreams()->first()->get('duration'));

        // 计算每秒传输速率
        $transmissionRate = $fileSize / $duration;

        // 计算目标速率的比例
        $rateRatio = floor($transmissionRate / $rate);
        if ($rateRatio < 1 || $this->depth > 10) {
            $this->depth = 0;
            return $input;
        }

        //大于超过4倍时,分辨率降2级
        if ($rateRatio > 2) {
            $widthNew  = intval($width / 2);
            $heightNew = intval($height / 2);
            //如果宽或高到达最小值则不应用
            if ($widthNew >= 240 && $heightNew >= 240) {
                $width  = $widthNew;
                $height = $heightNew;
            }
        }

        //视频比特率按比率减少
        $bitRate = intval($bitRate / $rateRatio);

        //帧率按比率减少,最低为16
        $frameRate = intval(max($frameRate / $rateRatio, 16));

        //开始转码
        $video->filters()
            //设置分辨率
            ->resize(new Dimension($width, $height))
            ->framerate(new FrameRate($frameRate), $frameRate)
            ->synchronize();

        $format = new X264('aac');
        $format->setKiloBitrate($bitRate / 1024);

        //设置音频为标清
        $format->setAudioKiloBitrate(64);

        //执行压缩
        $video->save($format, $outputFile);
        copy($outputFile, $input);
        unlink($outputFile);
        return $this->compressTheVideo($input, $rate);
    }

    /**
     * 执行压缩视频
     * @param string     $input 视频文件路径
     * @param float|null $rate  目标速率
     * @return bool|string
     */
    public function make(string $input, float|null $rate = 131072): bool|string
    {
        $tempPath = '/tmp/' . md5($input) . '.mp4';
        if (!copy($input, $tempPath)) {
            return false;
        }
        return $this->compressTheVideo($tempPath, $rate);
    }
}
