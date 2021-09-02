<?php

namespace mgboot\bo;

use mgboot\constant\Regexp;
use mgboot\util\ArrayUtils;

final class Pager
{
    private int $recordTotal;
    private int $currentPage;
    private int $pageSize;
    private int $pageStep;

    private function __construct(int... $args)
    {
        $recordTotal = -1;
        $currentPage = -1;
        $pageSize = -1;
        $pageStep = -1;

        foreach ($args as $num) {
            if ($num < 0) {
                continue;
            }

            if ($recordTotal < 0) {
                $recordTotal = $num;
            } else if ($currentPage < 0) {
                $currentPage = $num;
            } else if ($pageSize < 0) {
                $pageSize = $num;
            } else if ($pageStep < 0) {
                $pageStep = $num;
            }
        }

        $this->recordTotal = $recordTotal < 0 ? 0 : $recordTotal;
        $this->currentPage = $currentPage < 1 ? 1 : $currentPage;
        $this->pageSize = $pageSize < 1 ? 20 : $pageSize;
        $this->pageStep = $pageStep < 1 ? 5 : $pageStep;
    }

    public static function create(int... $args): self
    {
        return new self(...$args);
    }

    public function toMap(array|string|null $includeFields = null): array
    {
        if (is_string($includeFields) && !empty($includeFields)) {
            $includeFields = preg_split(Regexp::COMMA_SEP, $includeFields);
        }

        if (!ArrayUtils::isStringArray($includeFields)) {
            $includeFields = [];
        }

        $pagination = [
            'recordTotal' => $this->recordTotal,
            'pageTotal' => ($this->recordTotal > 0) ? ceil($this->recordTotal / $this->pageSize) : 0,
            'currentPage' => $this->currentPage,
            'pageSize' => $this->pageSize,
            'pageStep' => $this->pageStep
        ];

        $pageList = [];

        if ($pagination['pageTotal'] > 0) {
            $i = ceil($this->currentPage / $this->pageStep);
            $j = 1;

            for ($k = $this->pageStep * ($i - 1) + 1; $k <= $pagination['pageTotal']; $k++) {
                if ($j > $this->pageStep) {
                    break;
                }

                $pageList[] = $k;
                $j++;
            }
        }

        $pagination['pageList'] = $pageList;

        foreach (array_keys($pagination) as $key) {
            if (!in_array($key, $includeFields)) {
                unset($pagination[$key]);
            }
        }

        return $pagination;
    }

    public function toCommonMap(): array
    {
        return $this->toMap('recordTotal, pageTotal, currentPage, pageSize');
    }

    public function setRecordTotal(?int $recordTotal): self
    {
        if (is_int($recordTotal) && $recordTotal > 0) {
            $this->recordTotal = $recordTotal;
        }

        return $this;
    }

    public function setCurrentPage(?int $currentPage): self
    {
        if (is_int($currentPage) && $currentPage > 0) {
            $this->currentPage = $currentPage;
        }

        return $this;
    }

    public function setPageSize(?int $pageSize): self
    {
        if (is_int($pageSize) && $pageSize > 0) {
            $this->pageSize = $pageSize;
        }

        return $this;
    }

    public function setPageStep(?int $pageStep): self
    {
        if (is_int($pageStep) && $pageStep > 0) {
            $this->pageStep = $pageStep;
        }

        return $this;
    }
}
