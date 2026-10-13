// Aplica a altura sem style inline no HTML, mantendo a CSP restrita a arquivos locais.
document.querySelectorAll('[data-nivel]').forEach(agua => {
    const nivel = Number(agua.dataset.nivel);
    if (Number.isFinite(nivel)) agua.style.height = Math.max(0, Math.min(100, nivel)) + '%';
});
// O fragmento nunca chega aos access logs; remove-o também do histórico do navegador.
const redefinir = document.querySelector('[data-redefinir]');
if (redefinir && location.hash) {
    const token = new URLSearchParams(location.hash.slice(1)).get('token');
    if (token && /^[a-f0-9]{64}$/.test(token)) redefinir.elements.token.value = token;
    history.replaceState(null, '', location.pathname);
}
// Gráficos leves em SVG, sem bibliotecas ou dados inventados.
// O PHP só inclui este bloco JSON quando existem leituras para o painel.
const dados = document.getElementById('dados-graficos');
if (dados) {
    // A ordem recebida já vai da leitura mais antiga à mais recente.
    const leituras = JSON.parse(dados.textContent);
    // Elementos SVG precisam do namespace próprio, diferente dos elementos HTML.
    const ns = 'http://www.w3.org/2000/svg';
    /** Cria uma forma ou rótulo SVG e atribui texto sem interpretá-lo como HTML. */
    function elemento(nome, atributos, texto) {
        const item = document.createElementNS(ns, nome);
        Object.entries(atributos).forEach(([chave, valor]) => item.setAttribute(chave, valor));
        if (texto !== undefined) item.textContent = texto;
        return item;
    }
    // Cada contêiner informa a coluna e a unidade usando atributos data-* do HTML.
    document.querySelectorAll('.grafico').forEach(container => {
        // Redesenha apenas quando a largura muda; os rótulos não encolhem no celular.
        let larguraAnterior = 0;
        function desenhar() {
            const largura = Math.round(container.getBoundingClientRect().width);
            if (!largura || largura === larguraAnterior) return;
            larguraAnterior = largura;
            const esquerda = 52;
            const direita = largura - 16;
            // Colunas decimais do PDO podem chegar como texto; Number permite fazer os cálculos.
            const valores = leituras.map(leitura => Number(leitura[container.dataset.campo]));
            const nivel = container.dataset.campo === 'nivel_reservatorio';
            // Nível usa escala fixa de 0 a 100; temperatura ganha uma margem de cinco graus.
            const minimo = nivel ? 0 : Math.floor(Math.min(...valores) - 5);
            const maximo = nivel ? 100 : Math.ceil(Math.max(...valores) + 5);
            // viewBox mantém as proporções; a descrição acessível já está no contêiner.
            const svg = elemento('svg', { viewBox: `0 0 ${largura} 244`, 'aria-hidden': 'true' });
            // Desenha cinco linhas horizontais e os valores do eixo vertical.
            for (let i = 0; i <= 4; i++) {
                const y = 20 + i * 40;
                svg.append(elemento('line', { x1: esquerda, y1: y, x2: direita, y2: y }));
                svg.append(elemento('text', { x: esquerda - 10, y: y + 4, 'text-anchor': 'end' },
                    (maximo - i * (maximo - minimo) / 4).toLocaleString('pt-BR', { maximumFractionDigits: 1 })));
            }
            // Converte medições em coordenadas; no SVG o eixo Y cresce de cima para baixo.
            // Uma única leitura fica centralizada, evitando dividir por zero no eixo X.
            const pontos = valores.map((valor, i) => ({
                x: valores.length === 1 ? (esquerda + direita) / 2 : esquerda + i * (direita - esquerda) / (valores.length - 1),
                y: 180 - (valor - minimo) / (maximo - minimo) * 160
            }));
            // A polyline conecta as leituras e os círculos destacam cada amostra.
            svg.append(elemento('polyline', { points: pontos.map(p => p.x + ',' + p.y).join(' ') }));
            pontos.forEach((p, i) => {
                const ponto = elemento('circle', { cx: p.x, cy: p.y, r: 4 });
                // title mostra uma dica ao passar o mouse, com a data recebida e o valor exato.
                ponto.append(elemento('title', {}, leituras[i].data_exibicao + ': ' +
                    valores[i].toLocaleString('pt-BR') + ' ' + container.dataset.unidade));
                svg.append(ponto);
            });
            // Separa data e hora em duas linhas, legíveis mesmo nos menores gráficos.
            const rotulo = (leitura, x, alinhamento) => {
                const [data, hora] = leitura.data_exibicao.split(' ');
                svg.append(elemento('text', { x, y: 211, 'text-anchor': alinhamento }, data.slice(0, 5)));
                svg.append(elemento('text', { x, y: 229, 'text-anchor': alinhamento }, hora.slice(0, 5)));
            };
            // Uma leitura recebe um único rótulo central, sem repetir o mesmo horário.
            if (leituras.length === 1) {
                rotulo(leituras[0], pontos[0].x, 'middle');
            } else {
                rotulo(leituras[0], esquerda, 'start');
                rotulo(leituras[leituras.length - 1], direita, 'end');
            }
            // Adiciona o gráfico completo ao DOM de uma só vez.
            container.replaceChildren(svg);
        }
        desenhar();
        new ResizeObserver(desenhar).observe(container);
    });
}

// O navegador valida os campos antes do evento submit.
// O encadeamento opcional permite usar este script também em páginas sem formulário.
document.querySelector('[data-leitura]')?.addEventListener('submit', event => {
    // Evita cliques repetidos e informa que a requisição está em andamento.
    const botao = event.currentTarget.querySelector('button[type="submit"]');
    botao.disabled = true;
    botao.textContent = 'Salvando…';
});
// Ao voltar pelo histórico, o navegador pode restaurar o botão desabilitado do cache.
window.addEventListener('pageshow', () => {
    const botao = document.querySelector('[data-leitura] button[type="submit"]');
    if (botao) { botao.disabled = false; botao.textContent = 'Salvar leitura'; }
});
