<?php

namespace R3gis\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;

/**
 * Acme\BlogBundle\Entity\BlogComment
 *
 * @ORM\Table(name="geobi.user")
 * @ORM\Entity(repositoryClass="R3gis\AppBundle\Entity\UserRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class User implements AdvancedUserInterface
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="us_id", type="integer", nullable=false)
     * @ORM\Id
     * # -> auto... o sequence @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    
    /**
     * @var string $name
     *
     * @ORM\Column(name="us_name", type="string", nullable=false)
     */
    private $name;
    
    /**
     * @var string $email
     *
     * @ORM\Column(name="us_email", type="string", nullable=false, unique=true)
     */
    private $email;

    /**
     * @ORM\ManyToOne(targetEntity="Group")
     * @ORM\JoinColumn(name="gr_id", referencedColumnName="gr_id", nullable=false)
     */
    private $group;
    
    /**
     * @var string $status
     *
     * @ORM\Column(name="us_status", type="string", length=1, nullable=false)
     */
    private $status;
    
    /**
     * @var string
     * 
     * @ORM\ManyToOne(targetEntity="Language")
     * @ORM\JoinColumn(name="lang_id", referencedColumnName="lang_id")
     */
    private $language;

    /**
     * @var string $password
     * 
     * @ORM\Column(name="us_password", type="string", nullable=false)
     */
    private $password;

    /**
     * @var string $validationHash
     * 
     * @ORM\Column(name="us_validation_hash", type="string", length=32, nullable=false)
     */
    private $validationHash;
    
    /**
     * @var \DateTime $validationHashCreatedTime;
     * 
     * @ORM\Column(name="us_validation_hash_created_time", type="datetime", nullable=false)
     */
    private $validationHashCreatedTime;
    
    
    /**
     * @var string $resetPasswordHash
     * 
     * @ORM\Column(name="us_reset_password_hash", type="string", length=32, nullable=false)
     */
    private $resetPasswordHash;
    
    /**
     * @var \DateTime $resetPasswordHashCreatedTime;
     * 
     * @ORM\Column(name="us_reset_password_hash_created_time", type="datetime", nullable=false)
     */
    private $resetPasswordHashCreatedTime;
    
    /**
     * @var \DateTime $pwLastChange
     * 
     * @ORM\Column(name="us_pw_last_change", type="datetime")
     */
    private $pwLastChange;
    
    /**
     * @var string $lastIp
     * 
     * @ORM\Column(name="us_last_ip", type="string")
     */
    private $lastIp;
    
    /**
     * @var \DateTime $lastLogin
     * 
     * @ORM\Column(name="us_last_login", type="datetime")
     */
    private $lastLogin;
    
    /**
     * User that modified $this one.
     * 
     * @var User modifiedByUser 
     * 
     * #@ORM\ManyToOne(targetEntity="User")
     * #@ORM\JoinColumn(name="us_mod_user", referencedColumnName="us_id")
     */
    private $modifiedByUser;
    
    /**
     * @var \DateTime modifiedDate
     * 
     * @ORM\Column(name="us_mod_date", type="datetime")
     */
    private $modifiedDate;
    
    
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
     * @return User
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
     * Set password
     *
     * @param string $password
     * @return User
     */
    public function setPassword($pw)
    {
        $this->password = $pw;

        return $this;
    }

    /**
     * Get password
     *
     * @return string 
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string 
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set group
     *
     * @param \R3gis\AppBundle\Entity\Group $group
     * @return User
     */
    public function setGroup(\R3gis\AppBundle\Entity\Group $group = null)
    {
        $this->group = $group;
        
        return $this;
    }

    /**
     * Get group
     *
     * @return \R3gis\AppBundle\Entity\Group 
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Set language
     *
     * @param \R3gis\AppBundle\Entity\Language $language
     * @return User
     */
    public function setLanguage(\R3gis\AppBundle\Entity\Language $language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return \R3gis\AppBundle\Entity\Language 
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return User
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /*
     * @return \DateTime
     */
    public function getPwLastChange(){
        return $this->pwLastChange;
    }

    /**
     * @param \DateTime $pwLastChange
     */
    public function setPwLastChange(\DateTime $pwLastChange=null){
        $this->pwLastChange = $pwLastChange;
        return $this;
    }

    public function getLastIp(){
        return $this->lastIp;
    }

    public function setLastIp($lastIp){
        $this->lastIp = $lastIp;
        return $this;
    }

    /*
     * @return \DateTime
     */
    public function getLastLogin(){
        return $this->lastLogin;
    }

    /**
     * @param \DateTime $lastLogin
     */
    public function setLastLogin(\DateTime $lastLogin=null){
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getModifiedByUser(){
        return $this->modifiedByUser;
    }

    public function setModifiedByUser($modifiedByUser){
        $this->modifiedByUser = $modifiedByUser;
        return $this;
    }

    /*
     * @return \DateTime
     */
    public function getModifiedDate(){
        return $this->modifiedDate;
    }

    /**
     * @param \DateTime $modifiedDate
     */
    public function setModifiedDate(\DateTime $modifiedDate){
        $this->modifiedDate = $modifiedDate;
        return $this;
    }
    
    public function eraseCredentials() {
        
    }

    public function getRoles() {
        if($this->getGroup()==null){
            return array();
        }
        return array('ROLE_'.$this->group->getName());
    }

    public function getSalt() {
        if($this->password!=null && strlen($this->password)>29) {
            return substr($this->password, 7, 22);
        }
        return null;
    }

    public function getUsername() {
        return $this->getEmail();
    }

    public function isAccountNonExpired() {
        return true;
    }

    public function isAccountNonLocked() {
        return true;
    }

    public function isCredentialsNonExpired() {
        return true;
    }

    public function isEnabled() {
        return $this->status==='E';
    }

    public function getValidationHash(){
        return $this->validationHash;
    }

    public function setValidationHash($validationHash){
        $this->validationHash = $validationHash;
        return $this;
    }

    /*
     * @return \DateTime
     */
    public function getValidationHashCreatedTime(){
        return $this->validationHashCreatedTime;
    }

    /**
     * @param \DateTime $hashCreatedTime
     */
    public function setValidationHashCreatedTime(\DateTime $hashCreatedTime=null){
        $this->validationHashCreatedTime = $hashCreatedTime;
        return $this;
    }
    
    public function getResetPasswordHash(){
        return $this->resetPasswordHash;
    }

    public function setResetPasswordHash($resetPasswordHash){
        $this->resetPasswordHash = $resetPasswordHash;
        return $this;
    }

    /*
     * @return \DateTime
     */
    public function getResetPasswordHashCreatedTime(){
        return $this->resetPasswordHashCreatedTime;
    }

    /**
     * @param \DateTime $resetPasswordHashCreatedTime
     */
    public function setResetPasswordHashCreatedTime(\DateTime $resetPasswordHashCreatedTime=null){
        $this->resetPasswordHashCreatedTime = $resetPasswordHashCreatedTime;
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
