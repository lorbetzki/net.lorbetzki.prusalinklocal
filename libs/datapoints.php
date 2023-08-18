<?php

$DP = [
    //  Topicpath,		        Description,										          Type,   SymconProfile, Sort

      ['temp-bed',				    $this->Translate('Temperature bed'),			'FLOAT', 'PRLL_Temp', 10          ],
      ['temp-nozzle',			    $this->Translate('Temperature nozzle'),		'FLOAT', 'PRLL_Temp', 10          ],
      ['material',				    $this->Translate('loaded material'),			'STRING', '', 20                  ],
      ['state',					      $this->Translate('printer status'),				'STRING', '', 20                  ],
      ['estimatedPrintTime',	$this->Translate('estimated printtime'),	'INT', '~UnixTimestampTime', 30  ],
      ['name',					      $this->Translate('filename'),						  'STRING', '', 20                 ],
      ['completion',		    	$this->Translate('progress'),						  'FLOAT', '~Progress', 20         ],
      ['printTime',			    	$this->Translate('print time'),						'INT', '~UnixTimestampTime', 30  ],
      ['printTimeLeft',		   	$this->Translate('print time left'),			'INT', '~UnixTimestampTime', 30  ],
      ['printTimeReady',		  $this->Translate('printing expected to be ready'),			'INT', '~UnixTimestamp', 30  ]
];
