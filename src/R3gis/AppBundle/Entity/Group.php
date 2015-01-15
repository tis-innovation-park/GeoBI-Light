<?php

namespace R3gis\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Acme\BlogBundle\Entity\BlogComment
 *
 * @ORM\Table(name="geobi.groups")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Group
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="gr_id", type="integer", nullable=false)
     * @ORM\Id
     */
    private $id;
    
    /**
     * @var string $name
     *
     * @ORM\Column(name="gr_name", type="string", nullable=false)
     */
    private $name;
    
    /**
     * User that modified $this group.
     * 
     * @var User modifiedByUser 
     * 
     * #@ORM\ManyToOne(targetEntity="User")
     * #@ORM\JoinColumn(name="gr_mod_user", referencedColumnName="us_id", nullable=false)
     */
    private $modifiedByUser;
    
    /**
     * @var \DateTime modifiedDate
     * 
     * @ORM\Column(name="gr_mod_date", type="datetime", nullable=false)
     */
    private $modifiedDate;
    
    /**
     * Set id
     *
     * @param integer $id
     * @return Group
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Set name
     *
     * @param string $name
     * @return Group
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
    
    public function getModifiedByUser(){
        return $this->modifiedByUser;
    }

    public function setModifiedByUser($modifiedByUser){
        $this->modifiedByUser = $modifiedByUser;
        return $this;
    }

    public function getModifiedDate(){
        return $this->modifiedDate;
    }

    public function setModifiedDate($modifiedDate){
        $this->modifiedDate = $modifiedDate;
        return $this;
    }
    
    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function preUpdate()
    {
        $this->setModifiedDate( new \DateTime(date('Y-m-d H:i:s')));
    }
}
