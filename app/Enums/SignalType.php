<?php

namespace App\Enums;

enum SignalType: string
{
    case IncreaseNegativeSampling = 'INCREASE_NEGATIVE_SAMPLING';
    case DecreaseNegativeSampling = 'DECREASE_NEGATIVE_SAMPLING';
    case InjectEdgeCases = 'INJECT_EDGE_CASES';
    case ImproveConversationStructure = 'IMPROVE_CONVERSATION_STRUCTURE';
    case ReduceDuplicates = 'REDUCE_DUPLICATES';
    case IncreaseDataDiversity = 'INCREASE_DATA_DIVERSITY';
    case TightenCriticThreshold = 'TIGHTEN_CRITIC_THRESHOLD';
    case RelaxCriticThreshold = 'RELAX_CRITIC_THRESHOLD';
    case AugmentUnderrepresentedTasks = 'AUGMENT_UNDERREPRESENTED_TASKS';
    case ReviewGenerationPrompt = 'REVIEW_GENERATION_PROMPT';
}
