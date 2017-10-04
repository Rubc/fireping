<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Device
 *
 * @ORM\Table(name="device")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DeviceRepository")
 * @ApiResource(attributes={
 *     "normalization_context"={"groups"={"device"}},
 *     "denormalization_context"={"groups"={"device"}}
 * })
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Device
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"device"})
     */
    private $id;

    /**
     * @var domain
     *
     * @ORM\ManyToOne(targetEntity="Domain", inversedBy="devices")
     * @ORM\JoinColumn(name="domain_id", referencedColumnName="id")
     * @Assert\NotBlank
     * @Groups({"device"})
     */
    private $domain;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     * @Assert\NotBlank
     * @Groups({"device"})
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="ip", type="string", length=255)
     * @Assert\NotBlank
     * @Groups({"device"})
     */
    private $ip;

    /**
     * @ORM\ManyToMany(targetEntity="SlaveGroup", inversedBy="devices")
     * @ORM\JoinTable(name="device_slavegroups",
     *      joinColumns={@ORM\JoinColumn(name="device_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="slavegroup_id", referencedColumnName="id")}
     *      )
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     * @Groups({"device"})
     */
    private $slavegroups;

    /**
     * @ORM\ManyToMany(targetEntity="Probe")
     * @ORM\JoinTable(name="device_probes",
     *      joinColumns={@ORM\JoinColumn(name="device_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="probe_id", referencedColumnName="id")}
     *      )
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     * @Groups({"device"})
     */
    private $probes;

    /**
     * @ORM\ManyToMany(targetEntity="AlertRule")
     * @ORM\JoinTable(name="device_alert_rules",
     *      joinColumns={@ORM\JoinColumn(name="device_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="alert_rule_id", referencedColumnName="id")}
     *      )
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     * @Groups({"device"})
     */
    private $alertRules;

    /**
     * @var alerts
     * @ORM\OneToMany(targetEntity="Alert", mappedBy="device")
     * @Groups({"device"})
     */
    private $alerts;


    /**
     * Get id
     *
     * @return int
     * @Groups({"device"})
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Device
     * @Groups({"device"})
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
     * @Groups({"device"})
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set ip
     *
     * @param string $ip
     *
     * @return Device
     * @Groups({"device"})
     */
    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * Get ip
     *
     * @return string
     * @Groups({"device"})
     */
    public function getIp()
    {
        return $this->ip;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->slavegroups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->probes = new \Doctrine\Common\Collections\ArrayCollection();
        $this->alertRules = new \Doctrine\Common\Collections\ArrayCollection();
        $this->alerts = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set domain
     *
     * @param \AppBundle\Entity\Domain $domain
     *
     * @return Device
     * @Groups({"device"})
     */
    public function setDomain(\AppBundle\Entity\Domain $domain = null)
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Get domain
     *
     * @return \AppBundle\Entity\Domain
     * @Groups({"device"})
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Add slavegroup
     *
     * @param \AppBundle\Entity\SlaveGroup $slavegroup
     *
     * @return Device
     */
    public function addSlaveGroup(\AppBundle\Entity\SlaveGroup $slavegroup)
    {
        $this->slavegroups[] = $slavegroup;

        return $this;
    }

    /**
     * Remove slavegroup
     *
     * @param \AppBundle\Entity\SlaveGroup $slavegroup
     */
    public function removeSlaveGroup(\AppBundle\Entity\SlaveGroup $slavegroup)
    {
        $this->slavegroups->removeElement($slavegroup);
    }

    /**
     * Get slavegroups
     *
     * @return \Doctrine\Common\Collections\Collection
     * @Groups({"device"})
     */
    public function getSlaveGroups()
    {
        return $this->slavegroups;
    }

    /**
     * Get probes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAllSlaveGroups()
    {
        $result = new ArrayCollection();
        foreach ($this->slavegroups as $slavegroup) {
            $result->add($slavegroup);
        }
        $parent = $this->getDomain();
        while ($parent != null) {
            foreach ($parent->getSlaveGroups() as $slavegroup) {
                $result->add($slavegroup);
            }
            $parent = $parent->getParent();
        }
        return $result;
    }

    /**
     * Add probe
     *
     * @param \AppBundle\Entity\Probe $probe
     *
     * @return Device
     */
    public function addProbe(\AppBundle\Entity\Probe $probe)
    {
        $this->probes[] = $probe;

        return $this;
    }

    /**
     * Remove probe
     *
     * @param \AppBundle\Entity\Probe $probe
     */
    public function removeProbe(\AppBundle\Entity\Probe $probe)
    {
        $this->probes->removeElement($probe);
    }

    /**
     * Get probes
     *
     * @return \Doctrine\Common\Collections\Collection
     * @Groups({"device"})
     */
    public function getProbes()
    {
        return $this->probes;
    }

    /**
     * Get probes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAllProbes()
    {
        $result = new ArrayCollection();
        foreach ($this->probes as $probe) {
            $result->add($probe);
        }
        $parent = $this->getDomain();
        while ($parent != null) {
              foreach ($parent->getProbes() as $probe) {
                      $result->add($probe);
                  }
            $parent = $parent->getParent();
        }
        return $result;
    }

    /**
     * Add alert
     *
     * @param \AppBundle\Entity\AlertRule $alertRule
     *
     * @return Device
     */
    public function addAlertRule(\AppBundle\Entity\AlertRule $alertRule)
    {
        $this->alertRules[] = $alertRule;

        return $this;
    }

    /**
     * Remove alert
     *
     * @param \AppBundle\Entity\AlertRule $alertRule
     */
    public function removeAlertRule(\AppBundle\Entity\AlertRule $alertRule)
    {
        $this->alertRules->removeElement($alertRule);
    }

    /**
     * Get alert rules
     *
     * @return \Doctrine\Common\Collections\Collection
     * @Groups({"device"})
     */
    public function getAlertRules()
    {
        return $this->alertRules;
    }

    /**
     * Get alert rules
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAllAlertRules()
    {
        $result = new ArrayCollection();
        foreach ($this->alertRules as $alertRule) {
            $result->add($alertRule);
        }
        $parent = $this->getDomain();
        while ($parent != null) {
            foreach ($parent->getAlertRules() as $alertRule) {
                $result->add($alertRule);
            }
            $parent = $parent->getParent();
        }
        return $result;
    }

    public function getActiveAlerts()
    {
        /*
        $criteria = Criteria::create()->where(Criteria::expr()->eq("active", 1));

        return $this->getAlerts()->matching($criteria);
        */
        $result = new ArrayCollection();

        $alerts = $this->getAlerts();
        foreach ($alerts as $alert) {
            if($alert->getActive()) {
                $result->add($alert);
            }
        }
        return $result;
    }

    public function getAlerts()
    {
        return $this->alerts;
    }

    public function __toString()
    {
        return $this->name;
    }

    /**
     * Add alert
     *
     * @param \AppBundle\Entity\Alert $alert
     *
     * @return Device
     */
    public function addAlert(\AppBundle\Entity\Alert $alert)
    {
        $this->alerts[] = $alert;

        return $this;
    }

    /**
     * Remove alert
     *
     * @param \AppBundle\Entity\Alert $alert
     */
    public function removeAlert(\AppBundle\Entity\Alert $alert)
    {
        $this->alerts->removeElement($alert);
    }

    /**
     * Get root domain
     */
    public function getRootDomain()
    {
        $domain = $this->getDomain();
        while ($domain->getParent() != null) {
            $domain = $domain->getParent();
        }
        return $domain;
    }
}
