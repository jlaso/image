<?php

namespace JLaso\GD;

use JLaso\GD\Text\FontRepository;
use JLaso\GD\Text\Shadow;
use JLaso\GD\Text\TextTools;

final class Image
{
    const ORIGIN = 'origin';
    const LAST_POS = 'last';

    const CIRCLE = 'circle';
    const ELLIPSE = 'ellipse';
    const POLYGON = 'polygon';
    const RECTANGLE = 'rectangle';
    const ARC_PIE = 'arc_pie';

    private $image;
    /** @var array */
    protected $colors;
    /** @var Point[] */
    protected $positions;
    /** @var FontRepository */
    protected $fontRepository;
    //---- last used
    /** @var Point */
    protected $position;
    /** @var int */
    protected $thickness;
    /** @var int */
    protected $color;
    /** @var string */
    protected $font;

    public function __construct($width, $height, $trueColor = true)
    {
        $this->width = $width;
        $this->height = $height;
        if ($trueColor) {
            // http://php.net/manual/en/function.imagecreatetruecolor.php
            $this->image = imagecreatetruecolor($width, $height);
            imagesavealpha($this->image, true);
        } else {
            $this->image = imagecreate($width, $height);
        }
        $this->setOrigin(new Point(0, 0));
    }

    /**
     * @return FontRepository
     */
    public function getFontRepository()
    {
        return $this->fontRepository;
    }

    /**
     * @param FontRepository $fontRepository
     * @return $this
     */
    public function setFontRepository($fontRepository)
    {
        $this->fontRepository = $fontRepository;

        return $this;
    }

    /**
     * @param $font
     * @return $this
     */
    public function setFont($font)
    {
        if (preg_match("/\.ttf$/", $font)) {
            $this->font = $font;
        } else {
            $this->font = $this->fontRepository->getFontFile($font);
        }

        return $this;
    }

    /**
     * @param Point $origin
     * @return $this
     */
    public function setOrigin(Point $origin)
    {
        $this->position = $origin;
        $this->createPosition(self::ORIGIN, $origin);

        return $this;
    }

    /**
     * @param Delta $delta
     * @return $this
     */
    public function move(Delta $delta)
    {
        $this->position->move($delta);
        $this->createPosition(self::LAST_POS, $this->position);

        return $this;
    }

    /**
     * @param $position
     * @return $this
     * @throws \Exception
     */
    public function moveTo($position)
    {
        $this->position = $this->toPoint($position);
        $this->createPosition(self::LAST_POS, $this->position);

        return $this;
    }

    /**
     * @param $whatever
     * @return array|Point
     * @throws \Exception
     */
    protected function toPoint($whatever)
    {
        if ($whatever instanceof Point) {
            return $whatever;
        }
        if (is_string($whatever)) {
            return $this->getPosition($whatever);
        }
        if (is_array($whatever) && (2 === count($whatever))) {
            $x = array_shift($whatever);
            $y = array_shift($whatever);
            return new Point($x, $y);
        }

        throw new \Exception('Position format not recognized in Image::toPoint ' . print_r($whatever, true));
    }

    /**
     * @param $colorName
     * @return $this
     * @throws \Exception
     * @internal param Color $color
     */
    public function setColor($colorName)
    {
        if (!isset($this->colors[$colorName])) {
            throw new \Exception("Color with {$colorName} name is not declared yet");
        }
        $this->color = $this->colors[$colorName];

        return $this;
    }

    /**
     * @param string $name
     * @param Color $color
     * @param int $alpha <p>
     * A value between 0 and 127.
     * 0 indicates completely opaque while
     * 127 indicates completely transparent.
     * </p>
     * @return $this
     */
    public function createColor($name, Color $color, $alpha = null)
    {
        if ($alpha) {
            $this->colors[$name] = imagecolorallocatealpha($this->image, $color->getRed(), $color->getGreen(), $color->getBlue(), $alpha);
        } else {
            $this->colors[$name] = imagecolorallocate($this->image, $color->getRed(), $color->getGreen(), $color->getBlue());
        }

        return $this;
    }

    /**
     * @param Color[] $colors
     * @param null|int $alpha
     * @return $this
     */
    public function createColors(array $colors, $alpha = null)
    {
        foreach ($colors as $colorName => $color) {
            $this->createColor($colorName, $color, $alpha);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param Point $point
     * @return $this
     */
    public function createPosition($name, Point $point)
    {
        $this->positions[$name] = $point;

        return $this;
    }

    /**
     * @param string $name
     * @return Point
     * @throws \Exception
     */
    public function getPosition($name)
    {
        if (!isset($this->positions[$name])) {
            throw new \Exception("Position with {$name} name is not declared yet");
        }

        return $this->positions[$name];
    }

    public function __destruct()
    {
        imagedestroy($this->image);

        return $this;
    }

    /**
     * @return bool
     */
    public function isTrueColor()
    {
        return imageistruecolor($this->image);
    }

    /**
     * @param GeometricShapeInterface|null $arg
     * @return $this
     * @throws \Exception
     */
    public function fill(GeometricShapeInterface $arg = null)
    {
        switch (1) {
            case (null === $arg):
                imagefill($this->image, $this->position->x, $this->position->y, $this->color);
                break;

            case ($arg instanceof Delta):
                /** @var Delta $arg */
                imagefilledrectangle($this->image, $this->position->x, $this->position->y, $arg->deltaX, $arg->deltaY, $this->color);
                break;

            case ($arg instanceof Polygon):
                /** @var Polygon $arg */
                imagefilledpolygon($this->image, $arg->asRawArray(), $arg->vertexNum(), $this->color);
                break;

            case ($arg instanceof Rectangle):
                /** @var Rectangle $arg */
                imagefilledrectangle($this->image, $arg->point1->x, $arg->point1->y, $arg->point2->x, $arg->point2->y, $this->color);
                break;

            case ($arg instanceof Ellipse):
                /** @var Ellipse $arg */
                imagefilledellipse($this->image, $arg->center->x, $arg->center->y, $arg->radiusX, $arg->radiusY, $this->color);
                break;

            case ($arg instanceof Circle):
                /** @var Circle $arg */
                imagefilledellipse($this->image, $arg->center->x, $arg->center->y, $arg->radius, $arg->radius, $this->color);
                break;

            // must be before Arc
            case ($arg instanceof ArcPie):
                /** @var ArcPie $arg */
                imagefilledarc($this->image, $arg->center->x, $arg->center->y, $arg->radiusX, $arg->radiusY, $arg->start, $arg->end, $this->color, IMG_ARC_PIE);
                break;

            // http://php.net/manual/es/function.imagefilledarc.php
            case ($arg instanceof Arc):
                /** @var Arc $arg */
                imagefilledarc($this->image, $arg->center->x, $arg->center->y, $arg->radiusX, $arg->radiusY, $arg->start, $arg->end, $this->color, null);
                break;

            default:
                throw new \Exception('Object not recognized "' . get_class($arg) . '" in Image::fill');

        }

        return $this;
    }

    /**
     * @param GeometricShapeInterface $arg
     * @return $this
     */
    public function stroke(GeometricShapeInterface $arg)
    {
        switch (1) {
            case ($arg instanceof Delta):
                /** @var Delta $arg */
                imagefilledrectangle($this->image, $this->position->x, $this->position->y, $arg->deltaX, $arg->deltaY, $this->color);
                break;

            case ($arg instanceof ArcPie):
                /** @var ArcPie $arg */
                imagearc($this->image, $arg->center->x, $arg->center->y, $arg->radiusX, $arg->radiusY, $arg->start, $arg->end, $this->color);
                break;

            case ($arg instanceof Arc):
                /** @var Arc $arg */
                imagearc($this->image, $arg->center->x, $arg->center->y, $arg->radiusX, $arg->radiusY, $arg->start, $arg->end, $this->color);
                break;

            case ($arg instanceof Circle):
                /** @var Circle $arg */
                imagefilledarc($this->image, $arg->center->x, $arg->center->y, $arg->radius, $arg->radius, 0, 360, $this->color, null);
                break;

            case ($arg instanceof Ellipse):
                /** @var Ellipse $arg */
                imagefilledellipse($this->image, $arg->center->x, $arg->center->y, $arg->radiusX, $arg->radiusY, $this->color);
                imagefilledellipse($this->image, $arg->center->x, $arg->center->y, $arg->radiusX - $this->thickness, $arg->radiusY - $this->thickness, $this->color);
                break;

        }

        return $this;
    }

    /**
     * @param int $thickness
     * @return $this
     */
    public function thickness($thickness)
    {
        $this->thickness = $thickness;
        imagesetthickness($this->image, $thickness);

        return $this;
    }

    /**
     * @param array $colors
     * @param int $alpha
     * @return $this
     */
    public function buildPalette($colors, $alpha = null)
    {
        if (!isset($colors['black'])) {
            $colors['black'] = new Color('000');
        }
        if (!isset($colors['white'])) {
            $colors['white'] = new Color('fff');
        }
        $this->createColor('transparent', new Color('fff'), 127);

        foreach ($colors as $name => $color) {
            $this->createColor($name, $color, $alpha);
        }

        return $this;
    }

    /**
     * @param bool $status
     * @return $this
     */
    public function saveAlphaBlending($status)
    {
        imagesavealpha($this->image, $status);

        return $this;
    }

    /**
     * @param bool $status
     * @return $this
     */
    public function alphaBlending($status)
    {
        imagealphablending($this->image, $status);

        return $this;
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function saveAsPng($fileName)
    {
        return imagepng($this->image, $fileName);
    }

    /**
     * @param string $objType
     * @param mixed $position
     * @param array ...$args   // http://php.net/manual/en/functions.arguments.php#functions.variable-arg-list
     * @return ArcPie|Circle|Ellipse|Polygon|Rectangle
     */
    public function factory($objType, $position, ...$args)
    {
        if (self::POLYGON !== $objType) {
            $position = $this->toPoint($position);
        }

        switch ($objType) {
            case self::CIRCLE:
                return new Circle($position, array_shift($args));
                break;

            case self::ELLIPSE:
                return new Ellipse($position, array_shift($args), array_shift($args));
                break;

            case self::POLYGON:
                return new Polygon($position);
                break;

            case self::RECTANGLE:
                return new Rectangle($position, $this->toPoint(array_shift($args)));
                break;

            case self::ARC_PIE:
                return new ArcPie($position, array_shift($args), array_shift($args), array_shift($args), array_shift($args));
                break;
        }
    }

    /**
     * @param int $w
     * @param int $h
     * @param string $text
     * @param Shadow|null $shadow
     * @param array $padding
     * @param int $angle
     * @return $this
     */
    public function writeText($w, $h, $text, Shadow $shadow = null, array $padding = [], $angle = 0)
    {
        // figure font-size out
        $info = TextTools::encloseText($text, $w, $h, $this->font, $padding, $angle);
        $padding = $info->paddings;
        $startX = $this->position->x + $padding[1] +
            ($w - $padding[1] - $padding[3] - $info->textWidth) / 2;
        $startY = $this->position->y + $padding[0] +
            ($h - $padding[0] - $padding[2] + $info->textHeight) / 2;

        // write shadow
        if ($shadow) {
            $prevColor = $this->color;
            $this->setColor($shadow->color);
            // offset expresses percentage
            $offset = intval($info->fontSize * $shadow->offset);
            imagettftext(
                $this->image,
                $info->fontSize,
                $angle,
                $startX + $offset, $startY + $offset,
                $this->color,
                $this->font,
                $text
            );
            $this->color = $prevColor;
        }

        // write text
        imagettftext(
            $this->image,
            $info->fontSize,
            $angle,
            $startX, $startY,
            $this->color,
            $this->font,
            $text
        );

        return $this;
    }

}