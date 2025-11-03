<?php

namespace Includes;

class Inmate {
    private $id;
    private $name;
    private $age;
    private $courtCases;

    public function __construct($id, $name, $age) {
        $this->id = $id;
        $this->name = $name;
        $this->age = $age;
        $this->courtCases = [];
    }

    public function addCourtCase($courtCase) {
        $this->courtCases[] = $courtCase;
    }

    public function getCourtCases() {
        return $this->courtCases;
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getAge() {
        return $this->age;
    }
}

class CourtCase {
    private $caseNumber;
    private $description;
    private $status;

    public function __construct($caseNumber, $description, $status) {
        $this->caseNumber = $caseNumber;
        $this->description = $description;
        $this->status = $status;
    }

    public function getCaseNumber() {
        return $this->caseNumber;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getStatus() {
        return $this->status;
    }
}