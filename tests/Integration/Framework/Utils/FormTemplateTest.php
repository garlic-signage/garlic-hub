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

namespace Tests\Integration\Framework\Utils;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\TemplateEngine\MustacheAdapter;
use App\Framework\TemplateEngine\MustacheEngineFactory;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use Defuse\Crypto\Key;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Security\MemorySession;

/**
 * The chain of a form page: fields -> FormBuilder -> FormTemplatePreparer -> PageResponse -> template generic/edit.
 * The keys the preparer writes have to be the ones the template reads.
 */
#[Group('io')]
class FormTemplateTest extends TestCase
{
	private CsrfToken $csrfToken;
	private DOMXPath $xpath;

	/**
	 * @throws FrameworkException
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());
		$builder         = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), $this->csrfToken);

		$form = $builder->prepareForm([
			'csrf'  => $builder->createField(['id' => 'csrf_token', 'type' => FieldType::CSRF, 'name' => 'csrf_token']),
			'id'    => $builder->createField(['id' => 'playlist_id', 'type' => FieldType::HIDDEN, 'name' => 'playlist_id', 'value' => 5]),
			'title' => $builder->createField(['id' => 'title', 'type' => FieldType::TEXT, 'name' => 'title', 'label' => 'Name <of> the list', 'value' => 'Lobby "main"']),
			'mode'  => $builder->createField(['id' => 'mode', 'type' => FieldType::DROPDOWN, 'name' => 'mode', 'label' => 'Mode', 'options' => ['a' => 'Auto', 'b' => 'Manual'], 'value' => 'b']),
		]);

		$page = new FormTemplatePreparer()->prepareUITemplate([
			'title'              => 'Edit playlist',
			'additional_css'     => [],
			'footer_modules'     => [],
			'template_name'      => 'generic/edit',
			'form_action'        => '/playlists/edit/5',
			'hidden'             => $form['hidden'],
			'visible'            => $form['visible'],
			'save_button_label'  => 'Save',
			'explanations'       => 'Change <b>the</b> name.',
			'additional_buttons' => [['ADDITIONAL_BUTTON_TYPE' => 'button', 'ADDITIONAL_BUTTON_NAME' => 'cancel', 'LANG_ADDITIONAL_BUTTON' => 'Cancel']],
		]);

		$response = new PageResponse($page);
		$adapter  = new MustacheAdapter(new MustacheEngineFactory()->create(__DIR__ . '/../../../../templates'));
		$html     = $adapter->render($response->getTemplate(), $response->getTemplateData());

		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<html><body>' . $html . '</body></html>');
		libxml_clear_errors();
		$this->xpath = new DOMXPath($document);
	}

	private function element(string $query): ?DOMElement
	{
		$nodes = $this->xpath->query($query);
		$node  = $nodes !== false ? $nodes->item(0) : null;

		return $node instanceof DOMElement ? $node : null;
	}

	private function text(string $query): string
	{
		return trim($this->element($query)->textContent ?? '');
	}

	private function attribute(string $query, string $attribute): string
	{
		return $this->element($query)?->getAttribute($attribute) ?? '';
	}

	public function testTheFormPostsToTheAction(): void
	{
		static::assertSame('/playlists/edit/5', $this->attribute('//form', 'action'));
		static::assertSame('post', $this->attribute('//form', 'method'));
		static::assertSame('Edit playlist', $this->text('//h1'));
	}

	public function testTheHiddenFieldsAreInTheForm(): void
	{
		static::assertSame($this->csrfToken->getToken(), $this->attribute('//form//input[@name="csrf_token"]', 'value'));
		static::assertSame('hidden', $this->attribute('//form//input[@name="csrf_token"]', 'type'));
		static::assertSame('5', $this->attribute('//form//input[@name="playlist_id"]', 'value'));
	}

	/**
	 * The label is escaped by the template, the field is HTML of the renderer.
	 */
	public function testTheVisibleFieldsHaveLabelAndInput(): void
	{
		static::assertSame('Name <of> the list', $this->text('//label[@for="title"]'));
		static::assertSame('Lobby "main"', $this->attribute('//input[@id="title"]', 'value'));
		static::assertSame('', $this->text('//span[@id="error_title"]'));
		static::assertSame('Manual', $this->text('//select[@id="mode"]/option[@selected]'));
	}

	public function testTheButtonsAreInTheForm(): void
	{
		static::assertSame('submit', $this->attribute('//form//button[@id="standardSubmit"]', 'type'));
		static::assertSame('Save', $this->text('//form//button[@id="standardSubmit"]'));
		static::assertSame('Cancel', $this->text('//form//button[@id="cancel"]'));
	}

	public function testTheExplanationIsEscapedText(): void
	{
		static::assertSame('Change <b>the</b> name.', $this->text('//form/p'));
		static::assertSame(0, (int) $this->xpath->evaluate('count(//form/p/b)'), 'The markup of the explanation must not become elements.');
	}
}
