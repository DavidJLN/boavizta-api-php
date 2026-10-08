<?php

declare(strict_types=1);

namespace Boavizta\Api\Enum;

/**
 * Impact criteria supported by BoaviztAPI (see GET /v1/utils/impact_criteria).
 */
enum Criterion: string
{
    case All = 'all';
    /** Climate change (kgCO2eq) */
    case Gwp = 'gwp';
    /** Climate change - biogenic */
    case GwpPb = 'gwppb';
    /** Climate change - fossil */
    case GwpPf = 'gwppf';
    /** Climate change - land use */
    case GwpPlu = 'gwpplu';
    /** Use of minerals and fossil resources (kgSbeq) */
    case Adp = 'adp';
    /** Abiotic depletion potential - elements */
    case Adpe = 'adpe';
    /** Abiotic depletion potential - fossil */
    case Adpf = 'adpf';
    /** Primary energy (MJ) */
    case Pe = 'pe';
    /** Acidification */
    case Ap = 'ap';
    /** Ecotoxicity */
    case Ctue = 'ctue';
    /** Human toxicity - cancer (the API key is upper-cased) */
    case CtuhC = 'CTUh_c';
    /** Human toxicity - non cancer (the API key is upper-cased) */
    case CtuhNc = 'CTUh_nc';
    /** Eutrophication - freshwater */
    case Epf = 'epf';
    /** Eutrophication - marine */
    case Epm = 'epm';
    /** Eutrophication - terrestrial */
    case Ept = 'ept';
    /** Ionising radiation */
    case Ir = 'ir';
    /** Land use */
    case Lu = 'lu';
    /** Ozone depletion */
    case Odp = 'odp';
    /** Particulate matter */
    case Pm = 'pm';
    /** Photochemical ozone formation */
    case Pocp = 'pocp';
    /** Water use */
    case Wu = 'wu';
    /** Material input per service unit (kg) */
    case Mips = 'mips';
    /** Net use of freshwater (m3) */
    case Fw = 'fw';
    /** Final energy consumption (MJ) */
    case Fe = 'fe';
}
