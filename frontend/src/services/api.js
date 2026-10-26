const API_BASE_URL = (process.env.REACT_APP_API_BASE_URL || '').replace(/\/$/, '');

export class ApiError extends Error {
  constructor(message, status = 0) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

export async function fetchFixtures(filters, signal) {
  if (!API_BASE_URL) {
    throw new ApiError('A URL da API não foi configurada. Defina REACT_APP_API_BASE_URL.', 0);
  }

  const params = new URLSearchParams({ per_page: '60' });

  if (filters.startDate) params.set('from', filters.startDate);
  if (filters.endDate) params.set('to', filters.endDate);
  if (filters.team.trim()) params.set('team', filters.team.trim());
  if (filters.competition) params.set('competition', filters.competition);

  let response;

  try {
    response = await fetch(`${API_BASE_URL}/api/v1/fixtures?${params.toString()}`, { signal });
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new ApiError('Não foi possível conectar à API do projeto.', 0);
  }

  if (!response.ok) {
    throw new ApiError(`A API retornou HTTP ${response.status}.`, response.status);
  }

  const payload = await response.json();
  return payload.data.map(normalizeFixture);
}

function normalizeFixture(fixture) {
  const startsAt = fixture.starts_at_brasilia || fixture.starts_at;
  const date = startsAt.slice(0, 10);
  const start = new Date(startsAt);

  return {
    id: fixture.id,
    date,
    dayLabel: new Intl.DateTimeFormat('pt-BR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      timeZone: 'America/Sao_Paulo',
    }).format(start),
    competition: fixture.competition?.name || 'Competição não informada',
    stage: fixture.round || fixture.competition?.season || 'Rodada não informada',
    status: formatStatus(fixture.status),
    finished: ['FT', 'AET', 'PEN'].includes(fixture.status),
    dateTime: startsAt,
    time: new Intl.DateTimeFormat('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: 'America/Sao_Paulo',
    }).format(start),
    home: normalizeTeam(fixture.home_team),
    away: normalizeTeam(fixture.away_team),
    venue: [fixture.venue, fixture.city].filter(Boolean).join(' · ') || 'Estádio e cidade não divulgados',
    broadcasts: (fixture.broadcasts || []).map(normalizeBroadcast),
  };
}

function normalizeTeam(team = {}) {
  return {
    name: team.name || 'Time não informado',
    abbreviation: team.short_name || team.name?.slice(0, 3).toUpperCase() || '---',
    crest: team.crest || null,
  };
}

function normalizeBroadcast(broadcast) {
  const typeLabels = {
    tv_open: 'TV aberta',
    tv_closed: 'TV fechada',
    streaming: 'Streaming',
    youtube: 'YouTube',
  };
  const accessLabels = {
    free: 'Gratuito',
    subscription: 'Assinatura',
    pay_per_view: 'Pay-per-view',
  };
  const access = accessLabels[broadcast.access_type] || 'A confirmar';

  return {
    name: broadcast.broadcaster?.name || 'Emissora não informada',
    category: typeLabels[broadcast.broadcaster?.type] || 'Outro',
    access,
    paid: access !== 'Gratuito',
    sourceUrl: broadcast.source_url,
  };
}

function formatStatus(status) {
  return { NS: 'Agendado', TBD: 'A confirmar', PST: 'Adiado', CANC: 'Cancelado', FT: 'Finalizado', AET: 'Finalizado após prorrogação', PEN: 'Finalizado nos pênaltis' }[status] || status || 'A confirmar';
}
