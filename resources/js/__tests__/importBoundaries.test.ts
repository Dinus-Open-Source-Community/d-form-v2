import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

/**
 * Architecture guard: static import graph must stay acyclic and respect layer
 * direction. `components/ui/**` is excluded from the cycle check because those
 * files are shadcn-vue library output whose barrel `index.ts` ↔ variant pattern
 * is a known static cycle, and AGENTS.md forbids editing them directly.
 */

const JS_ROOT = fileURLToPath(new URL('..', import.meta.url))
const UI_PREFIX = 'components/ui/'

/**
 * Wayfinder output folders are gitignored and may be absent in a clean CI
 * checkout, so their imports can stay unresolved. They are still scanned when
 * present; the guard only tolerates the unresolved specifier for these prefixes.
 */
const GENERATED_PREFIXES = ['actions/', 'routes/', 'wayfinder/']

const SOURCE_FILE = /\.(ts|tsx|mts|vue|js)$/
const RESOLVE_SUFFIXES = [
    '',
    '.ts',
    '.tsx',
    '.mts',
    '.d.ts',
    '.vue',
    '.js',
    '/index.ts',
    '/index.tsx',
    '/index.mts',
    '/index.vue',
    '/index.js',
]

const BUILDER_COLOCATED_ALLOWED = new Set([
    'formBanner.ts',
    'formBuilderPalette.ts',
    'formBuilderFieldFactory.ts',
    'fieldMapping.ts',
    'autosaveGuard.ts',
    'dirtyFields.ts',
    'optionImage.ts',
])

interface IGraphBuild {
    edges: Map<string, string[]>
    unresolved: string[]
}

function collectSourceFiles(directory: string): string[] {
    const entries = readdirSync(directory, { withFileTypes: true })
    return entries.flatMap((entry) => {
        const fullPath = join(directory, entry.name)
        if (entry.isDirectory()) {
            return entry.name === '__tests__' ? [] : collectSourceFiles(fullPath)
        }
        return SOURCE_FILE.test(entry.name) ? [fullPath] : []
    })
}

function extractSpecifiers(source: string): string[] {
    const patterns = [
        /import\s+(?:type\s+)?[^'"]*?from\s*['"]([^'"]+)['"]/g,
        /export\s+(?:type\s+)?[^'"]*?from\s*['"]([^'"]+)['"]/g,
        /import\s*['"]([^'"]+)['"]/g,
        /import\s*\(\s*['"]([^'"]+)['"]\s*\)/g,
    ]
    return patterns.flatMap((pattern) =>
        Array.from(source.matchAll(pattern), (match) => match[1]).filter(
            (specifier): specifier is string => typeof specifier === 'string',
        ),
    )
}

function toRepoPath(file: string): string {
    return relative(JS_ROOT, file).split('\\').join('/')
}

function specifierBase(specifier: string, fromFile: string): string | null {
    if (specifier.startsWith('@/')) return join(JS_ROOT, specifier.slice(2))
    if (specifier.startsWith('.')) return resolve(dirname(fromFile), specifier)
    return null
}

function buildGraph(files: string[]): IGraphBuild {
    const edges = new Map<string, string[]>()
    const unresolved: string[] = []

    for (const file of files) {
        const targets: string[] = []
        for (const specifier of extractSpecifiers(readFileSync(file, 'utf8'))) {
            const base = specifierBase(specifier, file)
            if (base === null) continue
            const hit = RESOLVE_SUFFIXES.map((suffix) => `${base}${suffix}`).find((candidate) =>
                existsSync(candidate),
            )
            if (hit === undefined) {
                unresolved.push(`${toRepoPath(file)} -> ${toRepoPath(base)}`)
                continue
            }
            targets.push(hit)
        }
        edges.set(file, targets)
    }

    return { edges, unresolved }
}

/** Tarjan SCC; returns only multi-file cycles (self-imports are meaningless here). */
function findCycles(graph: Map<string, string[]>): string[][] {
    const index = new Map<string, number>()
    const lowLink = new Map<string, number>()
    const onStack = new Set<string>()
    const stack: string[] = []
    const cycles: string[][] = []
    let nextIndex = 0

    const visit = (node: string): void => {
        index.set(node, nextIndex)
        lowLink.set(node, nextIndex)
        nextIndex += 1
        stack.push(node)
        onStack.add(node)

        for (const target of graph.get(node) ?? []) {
            if (!index.has(target)) {
                visit(target)
                lowLink.set(node, Math.min(lowLink.get(node) ?? 0, lowLink.get(target) ?? 0))
            } else if (onStack.has(target)) {
                lowLink.set(node, Math.min(lowLink.get(node) ?? 0, index.get(target) ?? 0))
            }
        }

        if (lowLink.get(node) !== index.get(node)) return

        const component: string[] = []
        let popped: string | undefined
        do {
            popped = stack.pop()
            if (popped === undefined) break
            onStack.delete(popped)
            component.push(popped)
        } while (popped !== node)

        if (component.length > 1) cycles.push(component)
    }

    for (const node of graph.keys()) {
        if (!index.has(node)) visit(node)
    }
    return cycles
}

const ALL_LAYERS = [
    'components/',
    'pages/',
    'layouts/',
    'hooks/',
    'lib/',
    'adapters/',
    'plugins/',
    'directives/',
    'utils/',
]
// `types/` is a contract-only folder, so generated runtime folders are forbidden too.
const TYPES_FORBIDDEN = [...ALL_LAYERS, ...GENERATED_PREFIXES]
const LIB_FORBIDDEN = [
    'components/',
    'pages/',
    'layouts/',
    'hooks/',
    'adapters/',
    'utils/',
    'plugins/',
    'directives/',
]
const CORE_FORBIDDEN = ['components/modules/', 'pages/', 'layouts/', 'hooks/']
const HOOKS_FORBIDDEN = ['pages/', 'layouts/', 'components/ui/', 'components/modules/']
const ADAPTERS_FORBIDDEN = ['pages/', 'layouts/', 'components/']
// Folder table: `pages/` is route-level only; no other layer may import it.
const MODULES_FORBIDDEN = ['pages/']

/** AGENTS.md allows builder factories/guards bound to the form-builder lifecycle to co-locate. */
function isBuilderColocatedModule(targetPath: string): boolean {
    const builderPrefix = 'components/modules/builder/'
    return (
        targetPath.startsWith(builderPrefix) &&
        BUILDER_COLOCATED_ALLOWED.has(targetPath.slice(builderPrefix.length))
    )
}

function findForbiddenEdges(
    graph: Map<string, string[]>,
    sourcePrefix: string,
    forbiddenTargets: string[],
    allow?: (targetPath: string) => boolean,
): string[] {
    const violations: string[] = []
    for (const [source, targets] of graph) {
        if (!toRepoPath(source).startsWith(sourcePrefix)) continue
        for (const target of targets) {
            const targetPath = toRepoPath(target)
            if (!forbiddenTargets.some((prefix) => targetPath.startsWith(prefix))) continue
            if (allow?.(targetPath) === true) continue
            violations.push(`${toRepoPath(source)} -> ${targetPath}`)
        }
    }
    return violations
}

const { edges, unresolved } = buildGraph(collectSourceFiles(JS_ROOT))

describe('import boundaries', () => {
    it('has no static import cycle outside components/ui', () => {
        const cycles = findCycles(edges)
            .map((component) => component.map(toRepoPath))
            .filter((component) => !component.every((path) => path.startsWith(UI_PREFIX)))
            .map((component) => `SCC(${component.sort().join(', ')})`)

        expect(cycles, cycles.join('\n')).toEqual([])
    })

    it('keeps lib free of UI-layer imports', () => {
        const violations = findForbiddenEdges(edges, 'lib/', LIB_FORBIDDEN)
        expect(violations, violations.join('\n')).toEqual([])
    })

    it('keeps types free of runtime-layer imports', () => {
        const violations = findForbiddenEdges(edges, 'types/', TYPES_FORBIDDEN)
        expect(violations, violations.join('\n')).toEqual([])
    })

    it('keeps adapters free of page/layout/component imports', () => {
        const violations = findForbiddenEdges(edges, 'adapters/', ADAPTERS_FORBIDDEN)
        expect(violations, violations.join('\n')).toEqual([])
    })

    it('keeps components/core free of module/page/hook/layout imports', () => {
        const violations = findForbiddenEdges(edges, 'components/core/', CORE_FORBIDDEN)
        expect(violations, violations.join('\n')).toEqual([])
    })

    it('keeps hooks free of pages, layouts, shadcn ui, and non-builder modules', () => {
        const violations = findForbiddenEdges(edges, 'hooks/', HOOKS_FORBIDDEN, isBuilderColocatedModule)
        expect(violations, violations.join('\n')).toEqual([])
    })

    it('keeps components/modules free of page imports', () => {
        const violations = findForbiddenEdges(edges, 'components/modules/', MODULES_FORBIDDEN)
        expect(violations, violations.join('\n')).toEqual([])
    })

    it('only leaves generated Wayfinder imports unresolved', () => {
        const unexpected = unresolved.filter(
            (entry) => !GENERATED_PREFIXES.some((prefix) => entry.split('-> ')[1]?.startsWith(prefix)),
        )
        expect(unexpected, unexpected.join('\n')).toEqual([])
    })
})
