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
        // Colunas decimais do PDO podem chegar como texto; Number permite fazer os cálculos.
        const valores = leituras.map(leitura => Number(leitura[container.dataset.campo]));
        const nivel = container.dataset.campo === 'nivel_reservatorio';
        // Nível usa escala fixa de 0 a 100; temperatura ganha uma margem de cinco graus.
        const minimo = nivel ? 0 : Math.floor(Math.min(...valores) - 5);
        const maximo = nivel ? 100 : Math.ceil(Math.max(...valores) + 5);
        // viewBox mantém as proporções; a descrição acessível já está no contêiner.
        const svg = elemento('svg', { viewBox: '0 0 480 230', 'aria-hidden': 'true' });
        // Desenha cinco linhas horizontais e os valores do eixo vertical.
        for (let i = 0; i <= 4; i++) {
            const y = 20 + i * 40;
            svg.append(elemento('line', { x1: 65, y1: y, x2: 460, y2: y }));
            svg.append(elemento('text', { x: 55, y: y + 4, 'text-anchor': 'end' },
                (maximo - i * (maximo - minimo) / 4).toLocaleString('pt-BR', { maximumFractionDigits: 1 })));
        }
        // Converte medições em coordenadas; no SVG o eixo Y cresce de cima para baixo.
        // Uma única leitura fica centralizada, evitando dividir por zero no eixo X.
        const pontos = valores.map((valor, i) => ({
            x: valores.length === 1 ? 262 : 65 + i * 395 / (valores.length - 1),
            y: 180 - (valor - minimo) / (maximo - minimo) * 160
        }));
        // A polyline conecta as leituras e os círculos destacam cada amostra.
        svg.append(elemento('polyline', { points: pontos.map(p => p.x + ',' + p.y).join(' ') }));
        pontos.forEach((p, i) => {
            const ponto = elemento('circle', { cx: p.x, cy: p.y, r: 4 });
            // title mostra uma dica ao passar o mouse, com a data recebida e o valor exato.
            ponto.append(elemento('title', {}, leituras[i].data_registro + ': ' +
                valores[i].toLocaleString('pt-BR') + ' ' + container.dataset.unidade));
            svg.append(ponto);
        });
        // Extrai dia/mês e hora/minuto do texto de data do banco, sem converter o fuso aqui.
        const rotulo = leitura => {
            const [data, hora] = leitura.data_registro.split(' ');
            return data.slice(8, 10) + '/' + data.slice(5, 7) + ' ' + hora.slice(0, 5);
        };
        // Rotula somente as extremidades para não sobrepor datas em telas estreitas.
        svg.append(elemento('text', { x: 65, y: 212 }, rotulo(leituras[0])));
        svg.append(elemento('text', { x: 460, y: 212, 'text-anchor': 'end' }, rotulo(leituras[leituras.length - 1])));
        // Adiciona o gráfico completo ao DOM de uma só vez.
        container.append(svg);
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
