<?php

namespace R3gis\AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * @ORM\Table(name="geobi.map_layer")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class MapLayer {

    /**
     * @var integer $id
     *
     * @ORM\Column(name="ml_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Map")
     * @ORM\JoinColumn(name="map_id", referencedColumnName="map_id", nullable=false)
     */
    protected $map;

    /**
     * @var integer $order
     *
     * @ORM\Column(name="ml_order", type="integer", nullable=false)
     */
    private $order;

    /**
     * @var string $name
     *
     * @ORM\Column(name="ml_name", type="string", nullable=false)
     */
    private $name;

    /**
     * @var string $modDate
     *
     * @ORM\Column(name="ml_mod_date", type="datetime", nullable=false)
     */
    private $modDate;

    /**
     * @var string $schema
     *
     * @ORM\Column(name="ml_schema", type="string", nullable=false)
     */
    private $tableSchema;

    /**
     * @var string $table
     *
     * @ORM\Column(name="ml_table", type="string", nullable=false)
     */
    private $tableName;

    /**
     * @var string $ckanPackage
     *
     * @ORM\Column(name="ml_ckan_package", type="string", nullable=false)
     */
    private $ckanPackage;

    /**
     * @var string $ckanId
     *
     * @ORM\Column(name="ml_ckan_id", type="string", nullable=false)
     */
    private $ckanId;

    /**
     * @var string $ckanSheet
     *
     * @ORM\Column(name="ml_ckan_sheet", type="string")
     */
    private $ckanSheet;

    /**
     * @var string $isShape
     *
     * @ORM\Column(name="ml_is_shape", type="boolean")
     */
    private $isShape;
    
    /**
     * @var string $dataColumn
     *
     * @ORM\Column(name="ml_data_column", type="string")
     */
    private $dataColumn;
    
    /**
     * @var string $spatialColumn
     *
     * @ORM\Column(name="ml_spatial_column", type="string")
     */
    private $spatialColumn;
    
    /**
     * @var string $dataColumnHeader
     *
     * @ORM\Column(name="ml_data_column_header", type="string")
     */
    private $dataColumnHeader;
    
    /**
     * @var string $spatialColumnHeader
     *
     * @ORM\Column(name="ml_spatial_column_header", type="string")
     */
    private $spatialColumnHeader;
    
    /**
     * @var string $temporary
     *
     * @ORM\Column(name="ml_temporary", type="boolean")
     */
    private $temporary;
    
    /**
     * @var string $noDataColor
     *
     * @ORM\Column(name="ml_nodata_color", type="string", length=6)
     */
    private $noDataColor;
    
    /**
     * @var string $outlineColor
     *
     * @ORM\Column(name="ml_outline_color", type="string", length=6)
     */
    private $outlineColor;
    
    /**
     * @var string $opacity
     *
     * @ORM\Column(name="ml_opacity", type="integer")
     */
    private $opacity;
    
    /**
     * @var string $divisions
     *
     * @ORM\Column(name="ml_divisions", type="integer")
     */
    private $divisions;
    
    /**
     * @var string $layerTypeId
     *
     * @ORM\Column(name="lt_id", type="integer")
     */
    private $layerTypeId;
    
    /**
     * @var string $divisionTypeId
     *
     * @ORM\Column(name="dt_id", type="integer")
     */
    private $divisionTypeId;
    
    /**
     * @var string $precision
     *
     * @ORM\Column(name="ml_precision", type="integer")
     */
    private $precision;
    
    /**
     * @var string $unit
     *
     * @ORM\Column(name="ml_unit", type="string")
     */
    private $unit;
    
    /**
     * @var string $minSize
     *
     * @ORM\Column(name="ml_min_size", type="integer")
     */
    private $minSize;
    
    /**
     * @var string $maxSize
     *
     * @ORM\Column(name="ml_max_size", type="integer")
     */
    private $maxSize;
    
    /**
     * @var string $sizeType
     *
     * @ORM\Column(name="ml_size_type", type="string")
     */
    private $sizeType;
    
    /**
     * @var string $symbol
     *
     * @ORM\Column(name="ml_symbol", type="string")
     */
    private $symbol;
    
    /**
     * @var string $active
     *
     * @ORM\Column(name="ml_active", type="boolean")
     */
    private $active = true;
    
    // Cloning the map_layer
    public function __clone() {
        $this->id = null;
        $this->map = null;
    }
    

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function setCreatedAtValue() {
        $this->modDate = new \DateTime();
    }

    /**
     * Set id
     *
     * @param integer $id
     * @return MapLayer
     */
    public function setId($id) {
        $this->id = $id;

        return $this;
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
     * Set order
     *
     * @param integer $order
     * @return MapLayer
     */
    public function setOrder($order) {
        $this->order = $order;

        return $this;
    }

    /**
     * Get order
     *
     * @return integer 
     */
    public function getOrder() {
        return $this->order;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return MapLayer
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
     * Set modDate
     *
     * @param \DateTime $modDate
     * @return MapLayer
     */
    public function setModDate($modDate) {
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
     * Set temporary
     *
     * @param boolean $temporary
     * @return MapLayer
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
     * Set map
     *
     * @param \R3gis\AppBundle\Entity\Map $map
     * @return MapLayer
     */
    public function setMap(\R3gis\AppBundle\Entity\Map $map) {
        $this->map = $map;

        return $this;
    }

    /**
     * Get map
     *
     * @return \R3gis\AppBundle\Entity\Map 
     */
    public function getMap() {
        return $this->map;
    }

    /**
     * Set tableSchema
     *
     * @param string $tableSchema
     * @return MapLayer
     */
    public function setTableSchema($tableSchema) {
        $this->tableSchema = $tableSchema;

        return $this;
    }

    /**
     * Get tableSchema
     *
     * @return string 
     */
    public function getTableSchema() {
        return $this->tableSchema;
    }

    /**
     * Set tableName
     *
     * @param string $tableName
     * @return MapLayer
     */
    public function setTableName($tableName) {
        $this->tableName = $tableName;

        return $this;
    }

    /**
     * Get tableName
     *
     * @return string 
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * Set ckanPackage
     *
     * @param string $ckanPackage
     * @return MapLayer
     */
    public function setCkanPackage($ckanPackage) {
        $this->ckanPackage = $ckanPackage;

        return $this;
    }

    /**
     * Get ckanPackage
     *
     * @return string 
     */
    public function getCkanPackage() {
        return $this->ckanPackage;
    }

    /**
     * Set ckanId
     *
     * @param string $ckanId
     * @return MapLayer
     */
    public function setCkanId($ckanId) {
        $this->ckanId = $ckanId;

        return $this;
    }

    /**
     * Get ckanId
     *
     * @return string 
     */
    public function getCkanId() {
        return $this->ckanId;
    }

    /**
     * Set ckanSheet
     *
     * @param string $ckanSheet
     * @return MapLayer
     */
    public function setCkanSheet($ckanSheet) {
        $this->ckanSheet = $ckanSheet;

        return $this;
    }

    /**
     * Get ckanSheet
     *
     * @return string 
     */
    public function getCkanSheet() {
        return $this->ckanSheet;
    }


    /**
     * Set isShape
     *
     * @param boolean $isShape
     * @return MapLayer
     */
    public function setIsShape($isShape)
    {
        $this->isShape = $isShape;

        return $this;
    }

    /**
     * Get isShape
     *
     * @return boolean 
     */
    public function getIsShape()
    {
        return $this->isShape;
    }

    /**
     * Set dataColumn
     *
     * @param string $dataColumn
     * @return MapLayer
     */
    public function setDataColumn($dataColumn)
    {
        $this->dataColumn = $dataColumn;

        return $this;
    }

    /**
     * Get dataColumn
     *
     * @return string 
     */
    public function getDataColumn()
    {
        return $this->dataColumn;
    }

    private function purgeInputColorText($colorText) {

        if (empty($colorText)) {
            return null;
        } else {
            if ($colorText[0] == '#') {
                // Remove #
                return substr($colorText, 1);
            } else {
                return $colorText;
            }
        }
    }
    private function putgeOutputColorText($colorText) {
        if ($colorText == '') {
            return null;
        } else {
            return "#{$colorText}";
        }
    }
            
    /**
     * Set noDataColor
     *
     * @param string $noDataColor
     * @return MapLayer
     */
    public function setNoDataColor($noDataColor)
    {
        $this->noDataColor = $this->purgeInputColorText($noDataColor);

        return $this;
    }

    /**
     * Get noDataColor
     *
     * @return string 
     */
    public function getNoDataColor()
    {
        return $this->putgeOutputColorText($this->noDataColor);
    }

    /**
     * Set outlineColor
     *
     * @param string $outlineColor
     * @return MapLayer
     */
    public function setOutlineColor($outlineColor)
    {
        $this->outlineColor = $this->purgeInputColorText($outlineColor);

        return $this;
    }

    /**
     * Get outlineColor
     *
     * @return string 
     */
    public function getOutlineColor()
    {
        
        return $this->putgeOutputColorText($this->outlineColor);
    }

    /**
     * Set opacity
     *
     * @param integer $opacity
     * @return MapLayer
     */
    public function setOpacity($opacity)
    {
        $this->opacity = $opacity;

        return $this;
    }

    /**
     * Get opacity
     *
     * @return integer 
     */
    public function getOpacity()
    { 
        return $this->opacity;
    }

    /**
     * Set divisions
     *
     * @param integer $divisions
     * @return MapLayer
     */
    public function setDivisions($divisions)
    {
        $this->divisions = $divisions;

        return $this;
    }

    /**
     * Get divisions
     *
     * @return integer 
     */
    public function getDivisions()
    {
        return $this->divisions;
    }

    /**
     * Set layerTypeId
     *
     * @param integer $layerTypeId
     * @return MapLayer
     */
    public function setLayerTypeId($layerTypeId)
    {
        $this->layerTypeId = $layerTypeId;

        return $this;
    }

    /**
     * Get layerTypeId
     *
     * @return integer 
     */
    public function getLayerTypeId()
    {
        return $this->layerTypeId;
    }

    /**
     * Set divisionTypeId
     *
     * @param integer $divisionTypeId
     * @return MapLayer
     */
    public function setDivisionTypeId($divisionTypeId)
    {
        $this->divisionTypeId = $divisionTypeId;

        return $this;
    }

    /**
     * Get divisionTypeId
     *
     * @return integer 
     */
    public function getDivisionTypeId()
    {
        return $this->divisionTypeId;
    }

    /**
     * Set precision
     *
     * @param integer $precision
     * @return MapLayer
     */
    public function setPrecision($precision)
    {
        $this->precision = $precision;

        return $this;
    }

    /**
     * Get precision
     *
     * @return integer 
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * Set unit
     *
     * @param string $unit
     * @return MapLayer
     */
    public function setUnit($unit)
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Get unit
     *
     * @return string 
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * Set minSize
     *
     * @param integer $minSize
     * @return MapLayer
     */
    public function setMinSize($minSize)
    {
        $this->minSize = $minSize;

        return $this;
    }

    /**
     * Get minSize
     *
     * @return integer 
     */
    public function getMinSize()
    {
        return $this->minSize;
    }

    /**
     * Set maxSize
     *
     * @param integer $maxSize
     * @return MapLayer
     */
    public function setMaxSize($maxSize)
    {
        $this->maxSize = $maxSize;

        return $this;
    }

    /**
     * Get maxSize
     *
     * @return integer 
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }

    /**
     * Set sizeType
     *
     * @param string $sizeType
     * @return MapLayer
     */
    public function setSizeType($sizeType)
    {
        $this->sizeType = $sizeType;

        return $this;
    }

    /**
     * Get sizeType
     *
     * @return string 
     */
    public function getSizeType()
    {
        return $this->sizeType;
    }

    /**
     * Set symbol
     *
     * @param string $symbol
     * @return MapLayer
     */
    public function setSymbol($symbol)
    {
        $this->symbol = $symbol;

        return $this;
    }

    /**
     * Get symbol
     *
     * @return string 
     */
    public function getSymbol()
    {
        return $this->symbol;
    }

    /**
     * Set active
     *
     * @param boolean $active
     * @return MapLayer
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean 
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set spatialColumn
     *
     * @param string $spatialColumn
     * @return MapLayer
     */
    public function setSpatialColumn($spatialColumn)
    {
        $this->spatialColumn = $spatialColumn;

        return $this;
    }

    /**
     * Get spatialColumn
     *
     * @return string 
     */
    public function getSpatialColumn()
    {
        return $this->spatialColumn;
    }

    /**
     * Set dataColumnHeader
     *
     * @param string $dataColumnHeader
     * @return MapLayer
     */
    public function setDataColumnHeader($dataColumnHeader)
    {
        $this->dataColumnHeader = $dataColumnHeader;

        return $this;
    }

    /**
     * Get dataColumnHeader
     *
     * @return string 
     */
    public function getDataColumnHeader()
    {
        return $this->dataColumnHeader;
    }

    /**
     * Set spatialColumnHeader
     *
     * @param string $spatialColumnHeader
     * @return MapLayer
     */
    public function setSpatialColumnHeader($spatialColumnHeader)
    {
        $this->spatialColumnHeader = $spatialColumnHeader;

        return $this;
    }

    /**
     * Get spatialColumnHeader
     *
     * @return string 
     */
    public function getSpatialColumnHeader()
    {
        return $this->spatialColumnHeader;
    }
}
