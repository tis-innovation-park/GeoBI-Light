<?php

namespace R3gis\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Acme\BlogBundle\Entity\BlogComment
 *
 * @ORM\Table(name="geobi.language")
 * @ORM\Entity
 */
class Language
{
    /**
     * @var string $lang_id
     *
     * @ORM\Column(name="lang_id", type="string", length=2, nullable=false)
     * @ORM\Id
     */
    private $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="lang_name", type="string")
     */
    private $name;

    /**
     * @var string nameEnglish
     * 
     * @ORM\Column(name="lang_name_en", type="string")
     */
    private $nameEnglish;
    
    /**
     * @var boolean active
     * 
     * @ORM\Column(name="lang_active", type="boolean")
     */
    private $active = false;
    
    /**
     * @var integer order
     * 
     * @ORM\Column(name="lang_order", type="integer")
     */
    private $order;
    
    
    

    /**
     * Set id
     *
     * @param string $id
     * @return Language
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return string 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Language
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
    
    public function getNameEnglish(){
        return $this->nameEnglish;
    }

    public function setNameEnglish($nameEnglish){
        $this->nameEnglish = $nameEnglish;
        return $this;
    }

    public function getActive(){
        return $this->active;
    }

    public function setActive($active){
        $this->active = $active;
        return $this;
    }

    public function getOrder(){
        return $this->order;
    }

    public function setOrder($order){
        $this->order = $order;
        return $this;
    }
}
