<?php

namespace GroundStack\GsMonitorProvider\Constants;

// use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class CustomFields {

    /**
     * commentField
     * custom comment field / output for constants editor or ext_conf_template.txt
     *
     * @param array $params
     * @return string
     */
    public function commentField(array $params): string {
        return "<p class='comment'>{$params['fieldValue']}</p>";
    }
}
