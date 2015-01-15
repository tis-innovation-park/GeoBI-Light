<?php

namespace R3gis\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * @ORM\Table(name="geobi.map_class")
 * @ORM\Entity
 */
class MapClass {

    /**
     * @var integer $id
     *
     * @ORM\Column(name="mc_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="MapLayer")
     * @ORM\JoinColumn(name="ml_id", referencedColumnName="ml_id", nullable=false)
     */
    protected $mapLayer;

    /**
     * @var integer $order
     *
     * @ORM\Column(name="mc_order", type="integer", nullable=false)
     */
    private $order;

    /**
     * @var string $name
     *
     * @ORM\Column(name="mc_name", type="string")
     */
    private $name;
    
    /**
     * @var string $number
     *
     * @ORM\Column(name="mc_number", type="float")
     */
    private $number;
    
    /**
     * @var string 
     *
     * @ORM\Column(name="mc_text", type="string")
     */
    private $text;
    
    /**
     * @var string $color
     *
     * @ORM\Column(name="mc_color", type="string", length=6)
     */
    private $color;
    
    

    // Cloning the map_layer
    public function __clone() {
        $this->id = null;
        $this->layer = null;
    }
    
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set order
     *
     * @param integer $order
     * @return MapClass
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get order
     *
     * @return integer 
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return MapClass
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set number
     *
     * @param float $number
     * @return MapClass
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get number
     *
     * @return float 
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set text
     *
     * @param string $text
     * @return MapClass
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string 
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set color
     *
     * @param string $color
     * @return MapClass
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string 
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set mapLayer
     *
     * @param \R3gis\AppBundle\Entity\MapLayer $mapLayer
     * @return MapClass
     */
    public function setMapLayer(\R3gis\AppBundle\Entity\MapLayer $mapLayer)
    {
        $this->mapLayer = $mapLayer;

        return $this;
    }

    /**
     * Get mapLayer
     *
     * @return \R3gis\AppBundle\Entity\MapLayer 
     */
    public function getMapLayer()
    {
        return $this->mapLayer;
    }
}
