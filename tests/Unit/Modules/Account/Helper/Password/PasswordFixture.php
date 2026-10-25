<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Account\Helper\Password;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Validation\Sanitizer;
use App\Modules\Account\Helper\Password\Parameters;
use App\Modules\Account\Helper\Password\Validator;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use Defuse\Crypto\Key;
use Tests\Support\MemorySession;

/**
 * The real parts of the password form input: parameters, CSRF token and validator work together.
 * Only the translator is a stub, it answers "module.key" and fails for a text nobody asked for.
 */
final class PasswordFixture
{
	public const string PATTERN = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';

	public readonly MemorySession $session;
	public readonly CsrfToken $csrfToken;
	public readonly Parameters $parameters;
	public readonly Validator $validator;
	public readonly FormBuilder $formBuilder;

	/**
	 * @throws \Exception
	 */
	public function __construct(Translator $translator)
	{
		$this->session    = new MemorySession();
		$this->csrfToken  = new CsrfToken(new Crypt(Key::createNewRandomKey()), $this->session);
		$this->parameters = new Parameters(new Sanitizer(), $this->session);
		$this->validator  = new Validator($translator, $this->parameters, $this->csrfToken);
		$this->formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), $this->csrfToken);
	}

	/**
	 * The first element of the html as DOM element, or null.
	 */
	public static function element(string $html): ?\DOMElement
	{
		$document = new \DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
		libxml_clear_errors();
		$inputs = (new \DOMXPath($document))->query('//input');
		$input  = $inputs === false ? null : $inputs->item(0);

		return $input instanceof \DOMElement ? $input : null;
	}

	/**
	 * @return \Closure(string, string): string
	 */
	public static function translation(): \Closure
	{
		return static fn(string $key, string $module): string => $module . '.' . $key;
	}

	/**
	 * A post of the form with the CSRF token of the session.
	 *
	 * @param array<string,mixed> $changes replaces or (null) removes entries
	 * @return array<string,mixed>
	 */
	public function post(array $changes = []): array
	{
		$post = ['csrf_token' => $this->csrfToken->getToken(), 'password' => 'Secret123', 'password_confirm' => 'Secret123'];
		foreach ($changes as $key => $value)
		{
			if ($value === null)
				unset($post[$key]);
			else
				$post[$key] = $value;
		}

		return $post;
	}
}
