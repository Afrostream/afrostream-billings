<?php
        
class DateRange extends ArrayIterator
{

	protected $oDate = null;
    protected $oStartDate = null;
    protected $oEndDate = null;
    protected $oInterval = null;
	
    public function __construct( DateTime $oStartDate, DateTime $oEndDate, DateInterval $oInterval = null )
    {
    	$this->oStartDate = $oStartDate;
        $this->oDate = clone $oStartDate;
        $this->oEndDate = $oEndDate;
        $this->oInterval = $oInterval;
    }
	
	public function next()
  	{
   		$this->oDate->add($this->oInterval);
   		return $this->oDate;
	}
	
	public function current()
    {
    	return $this->oDate;
	}
	
	public function valid()
    {
    	if ($this->oStartDate > $this->oEndDate)
    	{
        	return $this->oDate >= $this->oEndDate;
        }
        return $this->oDate <= $this->oEndDate;
   }

}

?>