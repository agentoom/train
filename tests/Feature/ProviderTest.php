<?php

use App\Actions\Providers\CreateProviderAction;
use App\Actions\Providers\TestProviderConnectionAction;
use App\Actions\Providers\UpdateProviderAction;
use App\DTOs\AIProviderConfigDTO;
use App\DTOs\ConnectionHealthDTO;
use App\Enums\AIProviderType;
use App\Livewire\Providers\ProviderForm;
use App\Livewire\Providers\ProviderList;
use App\Models\AIProvider;
use App\Models\User;
use App\Services\AI\ProviderFactory;
use App\Services\AI\ProviderHealthCheckService;
use App\Services\AI\ProviderResolverService;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GenericOpenAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\Settings\ProviderConfigurationService;
use Livewire\Livewire;

// --- ProviderFactory ---

test('ProviderFactory makes OpenAIProvider for OpenAI type', function () {
    $factory = app(ProviderFactory::class);
    $config = new AIProviderConfigDTO(type: AIProviderType::OpenAI, apiKey: 'test-key');
    expect($factory->make(AIProviderType::OpenAI, $config))->toBeInstanceOf(OpenAIProvider::class);
});

test('ProviderFactory makes AnthropicProvider for Anthropic type', function () {
    $factory = app(ProviderFactory::class);
    $config = new AIProviderConfigDTO(type: AIProviderType::Anthropic, apiKey: 'test-key');
    expect($factory->make(AIProviderType::Anthropic, $config))->toBeInstanceOf(AnthropicProvider::class);
});

test('ProviderFactory makes OpenRouterProvider for OpenRouter type', function () {
    $factory = app(ProviderFactory::class);
    $config = new AIProviderConfigDTO(type: AIProviderType::OpenRouter, apiKey: 'test-key');
    expect($factory->make(AIProviderType::OpenRouter, $config))->toBeInstanceOf(OpenRouterProvider::class);
});

test('ProviderFactory makes GenericOpenAIProvider for Generic type', function () {
    $factory = app(ProviderFactory::class);
    $config = new AIProviderConfigDTO(type: AIProviderType::Generic, apiKey: 'test-key');
    expect($factory->make(AIProviderType::Generic, $config))->toBeInstanceOf(GenericOpenAIProvider::class);
});

// --- Capabilities DTOs ---

test('OpenAIProvider returns correct capabilities', function () {
    $config = new AIProviderConfigDTO(type: AIProviderType::OpenAI, apiKey: 'key');
    $caps = (new OpenAIProvider($config))->getCapabilities();

    expect($caps->supportsJsonMode)->toBeTrue()
        ->and($caps->supportsStructuredOutput)->toBeTrue()
        ->and($caps->supportsStreaming)->toBeTrue()
        ->and($caps->maxContextWindow)->toBe(128000);
});

test('AnthropicProvider returns correct capabilities', function () {
    $config = new AIProviderConfigDTO(type: AIProviderType::Anthropic, apiKey: 'key');
    $caps = (new AnthropicProvider($config))->getCapabilities();

    expect($caps->supportsReasoning)->toBeTrue()
        ->and($caps->maxContextWindow)->toBe(200000);
});

test('GenericOpenAIProvider returns conservative capabilities', function () {
    $config = new AIProviderConfigDTO(type: AIProviderType::Generic, apiKey: 'key', baseUrl: 'http://localhost');
    $caps = (new GenericOpenAIProvider($config))->getCapabilities();

    expect($caps->supportsStructuredOutput)->toBeFalse()
        ->and($caps->supportsStreaming)->toBeFalse()
        ->and($caps->supportsTools)->toBeFalse();
});

// --- ProviderResolverService ---

test('ProviderResolverService resolves correct provider instance', function () {
    $user = User::factory()->create();
    $aiProvider = AIProvider::factory()->openai()->create(['user_id' => $user->id]);

    $instance = app(ProviderResolverService::class)->resolve($aiProvider);

    expect($instance)->toBeInstanceOf(OpenAIProvider::class);
});

// --- ProviderConfigurationService CRUD ---

test('ProviderConfigurationService creates a provider', function () {
    $user = User::factory()->create();
    $provider = app(ProviderConfigurationService::class)->create($user, [
        'label' => 'My OpenAI',
        'type' => AIProviderType::OpenAI->value,
        'api_key' => 'sk-test',
        'default_model' => 'gpt-4o-mini',
    ]);

    expect($provider->label)->toBe('My OpenAI')
        ->and($provider->user_id)->toBe($user->id)
        ->and($provider->type)->toBe(AIProviderType::OpenAI);
});

test('ProviderConfigurationService updates a provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id, 'label' => 'Old Label']);

    $updated = app(ProviderConfigurationService::class)->update($provider, [
        'label' => 'New Label',
        'type' => $provider->type->value,
    ]);

    expect($updated->label)->toBe('New Label');
});

test('ProviderConfigurationService deletes a provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);

    app(ProviderConfigurationService::class)->delete($provider);

    $this->assertSoftDeleted('ai_providers', ['id' => $provider->id]);
});

test('ProviderConfigurationService toggles enabled status', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id, 'is_enabled' => true]);
    $service = app(ProviderConfigurationService::class);

    expect($service->toggleEnabled($provider)->is_enabled)->toBeFalse();
});

// --- Actions ---

test('CreateProviderAction creates a provider for user', function () {
    $user = User::factory()->create();
    $provider = app(CreateProviderAction::class)->execute($user, [
        'label' => 'Test Provider',
        'type' => AIProviderType::OpenAI->value,
        'api_key' => 'sk-test-key',
    ]);

    expect($provider)->toBeInstanceOf(AIProvider::class)
        ->and($provider->user_id)->toBe($user->id);
});

test('UpdateProviderAction updates provider data', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);

    $updated = app(UpdateProviderAction::class)->execute($provider, [
        'label' => 'Updated',
        'type' => $provider->type->value,
    ]);

    expect($updated->label)->toBe('Updated');
});

test('TestProviderConnectionAction returns ConnectionHealthDTO', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->openai()->create(['user_id' => $user->id]);

    $mockHealth = new ConnectionHealthDTO(isHealthy: true, latencyMs: 120, availableModels: ['gpt-4o']);
    $mockService = Mockery::mock(ProviderHealthCheckService::class);
    $mockService->shouldReceive('check')->once()->with($provider)->andReturn($mockHealth);
    app()->instance(ProviderHealthCheckService::class, $mockService);

    $result = app(TestProviderConnectionAction::class)->execute($provider);

    expect($result->isHealthy)->toBeTrue()
        ->and($result->latencyMs)->toBe(120)
        ->and($result->availableModels)->toContain('gpt-4o');
});

// --- Policy enforcement ---

test('user cannot view another users provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($user->can('view', $provider))->toBeFalse();
});

test('user can view their own provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);

    expect($user->can('view', $provider))->toBeTrue();
});

test('user cannot update another users provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($user->can('update', $provider))->toBeFalse();
});

test('user cannot delete another users provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($user->can('delete', $provider))->toBeFalse();
});

// --- Livewire components ---

test('ProviderList renders for authenticated user', function () {
    $user = User::factory()->create();
    AIProvider::factory()->count(3)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ProviderList::class)
        ->assertOk();
});

test('ProviderList can delete own provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(ProviderList::class)
        ->call('deleteProvider', $provider->id)
        ->assertOk();

    $this->assertSoftDeleted('ai_providers', ['id' => $provider->id]);
});

test('ProviderList cannot delete another users provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->create(['user_id' => User::factory()->create()->id]);

    Livewire::actingAs($user)
        ->test(ProviderList::class)
        ->call('deleteProvider', $provider->id)
        ->assertForbidden();
});

test('ProviderForm creates provider on save', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProviderForm::class)
        ->set('label', 'My Provider')
        ->set('type', AIProviderType::OpenAI->value)
        ->set('apiKey', 'sk-test-key')
        ->call('save');

    $this->assertDatabaseHas('ai_providers', [
        'user_id' => $user->id,
        'label' => 'My Provider',
    ]);
});

test('ProviderForm validates required fields', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProviderForm::class)
        ->set('label', '')
        ->set('type', '')
        ->call('save')
        ->assertHasErrors(['label', 'type']);
});

test('providers index route requires authentication', function () {
    $this->get(route('providers.index'))->assertRedirect(route('login'));
});

test('authenticated user can access providers index', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('providers.index'))->assertOk();
});
