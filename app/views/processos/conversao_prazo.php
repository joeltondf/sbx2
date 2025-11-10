<?php
$formData = $formData ?? [];
$processId = (int)($processo['id'] ?? 0);

$conversionSteps = [
    [
        'key' => 'client',
        'label' => 'Cliente',
        'description' => 'Revise ou cadastre os dados do cliente.',
    ],
    [
        'key' => 'deadline',
        'label' => 'Prazo do serviço',
        'description' => 'Defina data de início e prazo de entrega.',
    ],
    [
        'key' => 'payment',
        'label' => 'Pagamento',
        'description' => 'Informe as condições financeiras.',
    ],
];
$currentStep = 'deadline';
$completedSteps = ['client'];
include __DIR__ . '/partials/conversion_steps.php';
?>
<div class="max-w-3xl mx-auto space-y-10">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Converter em Serviço &mdash; Prazo do Serviço</h1>
            <p class="text-sm text-gray-600">Defina a data de início e o prazo acordado para a entrega.</p>
        </div>
        <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="text-sm text-blue-600 hover:underline">&larr; Voltar para o processo</a>
    </div>

    <form
        action="processos.php?action=convert_to_service_deadline&id=<?php echo $processId; ?>"
        method="POST"
        class="bg-white shadow rounded-lg p-8 space-y-8"
        data-conversion-step="deadline"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="data_inicio_traducao">Data de início</label>
                <input type="date" id="data_inicio_traducao" name="data_inicio_traducao" value="<?php echo htmlspecialchars($formData['data_inicio_traducao'] ?? date('Y-m-d')); ?>" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="prazo_dias">Dias para entrega</label>
                <input type="number" min="1" id="prazo_dias" name="prazo_dias" value="<?php echo htmlspecialchars($formData['prazo_dias'] ?? $formData['traducao_prazo_dias'] ?? ''); ?>" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" required>
            </div>
        </div>

        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <a href="processos.php?action=convert_to_service_client&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Voltar</a>
            <div class="flex items-center space-x-3">
                <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none">
                    Salvar e continuar
                </button>
            </div>
        </div>
    </form>
</div>
