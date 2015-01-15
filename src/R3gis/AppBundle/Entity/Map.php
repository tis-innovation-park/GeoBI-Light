<?php

namespace R3gis\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * @ORM\Table(name="geobi.map")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class Map {

    /**
     * @var integer $id
     *
     * @ORM\Column(name="map_id", type="integer", nullable=false)
     * @ORM\Id
     * # -> auto... o sequence @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    
    /**
     * @var integer $idParent
     *
     * @ORM\Column(name="map_id_parent", type="integer")
     */
    private $idParent;

    /**
     * @var string $name
     *
     * @ORM\Column(name="map_name", type="string", nullable=false)
     */
    private $name;

    /**
     * @var string $description
     *
     * @ORM\Column(name="map_description", type="string")
     */
    private $description;

    /**
     * @var string $backgroundType
     *
     * @ORM\Column(name="map_background_type", type="string", nullable=false)
     */
    private $backgroundType;
    
    /**
     * @var boolean $backgroundActive
     *
     * @ORM\Column(name="map_background_active", type="boolean", nullable=false)
     */
    private $backgroundActive = true;

    /**
     * @var boolean $private
     *
     * @ORM\Column(name="map_private", type="boolean", nullable=false)
     */
    private $private;

    /**
     * @var string $insDate
     *
     * @ORM\Column(name="map_ins_date", type="datetime", nullable=false)
     */
    private $insDate;

    /**
     * @var string $modDate
     *
     * @ORM\Column(name="map_mod_date", type="datetime", nullable=false)
     */
    private $modDate;

    /**
     * @var string $clickCount
     *
     * @ORM\Column(name="map_click_count", type="integer", nullable=false)
     */
    private $clickCount;

    /**
     * @ORM\ManyToOne(targetEntity="Language")
     * @ORM\JoinColumn(name="lang_id", referencedColumnName="lang_id")
     */
    protected $language;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="us_id", referencedColumnName="us_id")
     */
    protected $user;

    /**
     * @var integer $id
     *
     * @ORM\Column(name="at_id", type="integer", nullable=false)
     * @ORM\JoinColumn(name="at_id", referencedColumnName="at_id")
     * #@ORM\ManyToOne(targetEntity="AreaType")
     */
    //protected $areaTypeId;

    /**
     * @var string $temporary
     *
     * @ORM\Column(name="map_temporary", type="boolean")
     */
    private $temporary;
    
    /**
     * @var string $hash
     *
     * @ORM\Column(name="map_hash", type="string", nullable=false)
     */
    private $hash;
    
    /**
     * @var string $userExtent
     *
     * @ORM\Column(name="map_user_extent", type="string")
     */
    private $userExtent;
    

    /**
     * Clone the object (and reset some patameters)
     */
    public function __clone() {
        $this->hash = null;
        $this->user = null;
        $this->insDate = new \DateTime();
        $this->modDate = null;
        $this->clickCount = 0;
        $this->idParent = $this->id;
        $this->id = null;
    }
    
    
    public function addClickCount() {
        $this->clickCount++;
    }

    /**
     * @ORM\PrePersist
     */
    public function prePersist() {
        $this->insDate = new \DateTime();
        // $this->modDate = new \DateTime();
        $this->clickCount = 0;
    }

    /**
     * @ORM\PreUpdate
     */
    public function preUpdate() {
        $this->modDate = new \DateTime();
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Map
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Set language
     *
     * @param \R3gis\AppBundle\Entity\Language $language
     * @return Map
     */
    public function setLanguage(\R3gis\AppBundle\Entity\Language $language = null) {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return \R3gis\AppBundle\Entity\Language 
     */
    public function getLanguage() {
        return $this->language;
    }

    /**
     * Set user
     *
     * @param \R3gis\AppBundle\Entity\User $user
     * @return Map
     */
    public function setUser(\R3gis\AppBundle\Entity\User $user = null) {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \R3gis\AppBundle\Entity\User 
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Set backgroundType
     *
     * @param string $backgroundType
     * @return Map
     */
    public function setBackgroundType($backgroundType) {
        if ($backgroundType == null) {
            $backgroundType = 'none';
        }
        if (!in_array($backgroundType, array('none', 'osm'))) {
            throw new \Exception("Invalid background type \"{$backgroundType}\"");
        }
        $this->backgroundType = $backgroundType;

        return $this;
    }

    /**
     * Get backgroundType
     *
     * @return string 
     */
    public function getBackgroundType() {
        return $this->backgroundType;
    }
    
    /**
     * Set backgroundActive
     *
     * @param boolean $private
     * @return Map
     */
    public function setBackgroundActive($backgroundActive) {
        $this->backgroundActive = $backgroundActive;

        return $this;
    }

    /**
     * Get backgroundActive
     *
     * @return boolean 
     */
    public function getBackgroundActive() {
        return $this->backgroundActive;
    }

    /**
     * Set private
     *
     * @param boolean $private
     * @return Map
     */
    public function setPrivate($private) {
        $this->private = $private;

        return $this;
    }

    /**
     * Get private
     *
     * @return boolean 
     */
    public function getPrivate() {
        return $this->private;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return Map
     */
    public function setDescription($description) {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Set insDate
     *
     * @param \DateTime $insDate
     * @return Map
     */
    public function setInsDate(\DateTime $insDate) {
        $this->insDate = $insDate;

        return $this;
    }

    /**
     * Get insDate
     *
     * @return \DateTime 
     */
    public function getInsDate() {
        return $this->insDate;
    }

    /**
     * Set modDate
     *
     * @param \DateTime $modDate
     * @return Map
     */
    public function setModDate(\DateTime $modDate) {

        $this->modDate = $modDate;

        return $this;
    }

    /**
     * Get modDate
     *
     * @return \DateTime 
     */
    public function getModDate() {
        return $this->modDate;
    }

    /**
     * Set clickCount
     *
     * @param integer $clickCount
     * @return Map
     */
    public function setClickCount($clickCount) {
        $this->clickCount = $clickCount;

        return $this;
    }

    /**
     * Get clickCount
     *
     * @return integer 
     */
    public function getClickCount() {
        return $this->clickCount;
    }

    /**
     * Set temporary
     *
     * @param boolean $temporary
     * @return Map
     */
    public function setTemporary($temporary) {
        $this->temporary = $temporary;

        return $this;
    }

    /**
     * Get temporary
     *
     * @return boolean 
     */
    public function getTemporary() {
        return $this->temporary;
    }


    /**
     * Set hash
     *
     * @param string $hash
     * @return Map
     */
    public function setHash($hash)
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * Get hash
     *
     * @return string 
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Set userExtent
     *
     * @param array $userExtent
     * @return Map
     */
    public function setUserExtent($userExtent)
    {
        $this->userExtent = json_encode($userExtent);

        return $this;
    }

    /**
     * Get userExtent
     *
     * @return array 
     */
    public function getUserExtent()
    {
        return json_decode($this->userExtent);
    }

    /**
     * Set idParent
     *
     * @param integer $idParent
     * @return Map
     */
    public function setIdParent($idParent)
    {
        $this->idParent = $idParent;

        return $this;
    }

    /**
     * Get idParent
     *
     * @return integer 
     */
    public function getIdParent()
    {
        return $this->idParent;
    }
}
