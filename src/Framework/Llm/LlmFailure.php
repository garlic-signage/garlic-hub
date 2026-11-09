<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace App\Framework\Llm;

use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use Throwable;

/**
 * Why an endpoint gives no models, as translation key: the user filling in the form of a provider has to know
 * what is wrong. Only the reason, never the answer of the endpoint.
 */
final class LlmFailure
{
	/**
	 * @param int $status the HTTP status of the answer, 0 without one
	 */
	public static function reasonOf(Throwable $e, int $status = 0): string
	{
		// the limit of the answer, see LlmClientFactory: Guzzle wraps the exception of on_headers
		for ($inner = $e; $inner !== null; $inner = $inner->getPrevious())
		{
			if ($inner instanceof LlmException && $inner->getTranslationKey() === 'llm_answer_too_large')
				return 'llm_answer_too_large';
		}

		$cause = $e->getPrevious() ?? $e;
		if ($cause instanceof ConnectTimeoutException || $cause instanceof NetworkTimeoutException || $cause instanceof ResponseTimeoutException)
			return 'llm_timeout';
		if ($cause instanceof NetworkException)
			return 'llm_connection_failed';

		return in_array($status, [401, 403], true) ? 'llm_unauthorized' : 'llm_no_models';
	}
}
