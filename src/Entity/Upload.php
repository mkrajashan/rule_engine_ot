<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTimeInterface;

#[ORM\Entity]
class Upload
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private $file_name;

    #[ORM\Column(type: 'string', nullable: true)]
    private $status;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $scan_result = [];

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $ciUploadId = null;

    #[ORM\Column(type: 'datetime')]
    private $uploaded_at;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $uploaded_by = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getFileName(): ?string {
        return $this->file_name;
    }

    public function setFileName(string $filename): self{
        $this->file_name = $filename;
        return $this;
    }

    public function getScanResult(): ?array {
        return $this->scan_result;
    }

    public function setScanResult(array $scanResult): self{
        $this->scan_result = $scanResult;
        return $this;
    }

    public function getCiUploadId(): ?string {
        return $this->ciUploadId;
    }

    public function setCiUploadId(string $ciUploadId): self{
        $this->ciUploadId = $ciUploadId;
        return $this;
    }

    public function getStatus(): ?string {
        return $this->status;
    }

    public function setStatus(?string $status): self{
        $this->status = $status;
        return $this;
    }

    public function getUploadedAt():DateTimeInterface{
        return $this->uploaded_at;
    }

    public function setUploadedAt(DateTimeInterface $uploadedAt):self{
        if ($this->uploaded_at === null) {
            $this->uploaded_at = new \DateTime();
        } else {
            $this->uploaded_at = $uploadedAt;
        }
        return $this;
    }

    public function getUploadedBy(): ?string
    {
        return $this->uploaded_by;
    }

    public function setUploadedBy(?string $uploaded_by): self
    {
        $this->uploaded_by = $uploaded_by;
        return $this;
    }
}