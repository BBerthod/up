import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

const MyPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{emerald.50}',
            100: '{emerald.100}',
            200: '{emerald.200}',
            300: '{emerald.300}',
            400: '{emerald.400}',
            500: '{emerald.500}',
            600: '{emerald.600}',
            700: '{emerald.700}',
            800: '{emerald.800}',
            900: '{emerald.900}',
            950: '{emerald.950}'
        }
    },
    components: {
        button: {
            borderRadius: '0.5rem',
            colorScheme: {
                dark: {
                    root: {
                        primary: {
                            background: '#10b981',
                            border: { color: '#10b981' },
                            color: '#ffffff',
                            hover: {
                                background: '#059669',
                                border: { color: '#059669' }
                            }
                        },
                        secondary: {
                            background: 'rgba(255,255,255,0.06)',
                            border: { color: 'rgba(255,255,255,0.10)' },
                            color: '#8b8b8e',
                            hover: {
                                background: 'rgba(255,255,255,0.10)',
                                border: { color: 'rgba(255,255,255,0.15)' },
                                color: '#ececef'
                            }
                        },
                        danger: {
                            background: 'transparent',
                            border: { color: 'rgba(239,68,68,0.3)' },
                            color: '#ef4444',
                            hover: {
                                background: 'rgba(239,68,68,0.1)',
                                border: { color: 'rgba(239,68,68,0.5)' }
                            }
                        }
                    }
                }
            }
        },
        datatable: {
            colorScheme: {
                dark: {
                    root: {
                        background: '#111113',
                        borderColor: 'transparent'
                    },
                    header: {
                        background: '#111113',
                        borderColor: 'transparent'
                    },
                    headerCell: {
                        background: '#111113',
                        color: '#8b8b8e',
                        borderColor: 'rgba(255,255,255,0.06)'
                    },
                    bodyCell: {
                        background: 'transparent',
                        color: '#ececef',
                        borderColor: 'rgba(255,255,255,0.06)'
                    },
                    row: {
                        background: 'transparent'
                    },
                    rowHover: {
                        background: 'rgba(255,255,255,0.03)'
                    },
                    sortIcon: {
                        color: '#5c5c5f'
                    }
                }
            }
        },
        dataview: {
            colorScheme: {
                dark: {
                    content: {
                        background: 'transparent',
                        borderColor: 'transparent',
                        color: '#ececef'
                    }
                }
            }
        },
        selectbutton: {
            colorScheme: {
                dark: {
                    root: {
                        borderRadius: '9999px'
                    },
                    toggleButton: {
                        background: 'transparent',
                        borderColor: 'rgba(255,255,255,0.10)',
                        color: '#5c5c5f',
                        hoverBackground: 'rgba(255,255,255,0.04)',
                        highlightBackground: 'rgba(255,255,255,0.10)',
                        highlightColor: '#ececef',
                        highlightBorderColor: 'rgba(255,255,255,0.15)'
                    }
                }
            }
        },
        tag: {
            borderRadius: '9999px',
            colorScheme: {
                dark: {
                    success: {
                        background: 'rgba(16,185,129,0.15)',
                        color: '#10b981'
                    },
                    danger: {
                        background: 'rgba(239,68,68,0.15)',
                        color: '#ef4444'
                    },
                    warning: {
                        background: 'rgba(245,158,11,0.15)',
                        color: '#f59e0b'
                    },
                    secondary: {
                        background: 'rgba(255,255,255,0.08)',
                        color: '#8b8b8e'
                    }
                }
            }
        },
        menu: {
            colorScheme: {
                dark: {
                    root: {
                        background: '#1a1a1d',
                        borderColor: 'rgba(255,255,255,0.10)',
                        shadow: '0 8px 30px rgba(0,0,0,0.5), 0 0 1px rgba(255,255,255,0.1)'
                    },
                    item: {
                        content: {
                            hoverBackground: 'rgba(255,255,255,0.05)',
                            color: '#8b8b8e'
                        },
                        icon: {
                            color: '#5c5c5f'
                        }
                    }
                }
            }
        },
        drawer: {
            colorScheme: {
                dark: {
                    root: {
                        background: '#111113',
                        borderColor: 'rgba(255,255,255,0.06)',
                        color: '#ececef'
                    }
                }
            }
        },
        inputtext: {
            colorScheme: {
                dark: {
                    root: {
                        background: 'rgba(255,255,255,0.04)',
                        border: { color: 'rgba(255,255,255,0.06)' },
                        color: '#ececef',
                        hover: {
                            borderColor: 'rgba(255,255,255,0.10)'
                        },
                        focus: {
                            borderColor: '#10b981',
                            shadow: '0 0 0 2px rgba(16,185,129,0.2)'
                        }
                    }
                }
            }
        },
        password: {
            colorScheme: {
                dark: {
                    root: {
                        background: 'rgba(255,255,255,0.04)',
                        border: { color: 'rgba(255,255,255,0.06)' },
                        color: '#ececef'
                    }
                }
            }
        },
        checkbox: {
            colorScheme: {
                dark: {
                    root: {
                        background: 'transparent',
                        borderColor: 'rgba(255,255,255,0.15)',
                        hover: {
                            borderColor: 'rgba(255,255,255,0.25)'
                        }
                    },
                    checked: {
                        background: '#10b981',
                        borderColor: '#10b981'
                    }
                }
            }
        },
        message: {
            colorScheme: {
                dark: {
                    success: {
                        background: 'rgba(16,185,129,0.1)',
                        borderColor: 'rgba(16,185,129,0.2)',
                        color: '#10b981'
                    },
                    error: {
                        background: 'rgba(239,68,68,0.1)',
                        borderColor: 'rgba(239,68,68,0.2)',
                        color: '#ef4444'
                    },
                    warn: {
                        background: 'rgba(245,158,11,0.1)',
                        borderColor: 'rgba(245,158,11,0.2)',
                        color: '#f59e0b'
                    }
                }
            }
        },
        select: {
            colorScheme: {
                dark: {
                    root: {
                        background: 'rgba(255,255,255,0.04)',
                        borderColor: 'rgba(255,255,255,0.06)',
                        color: '#ececef',
                        hover: {
                            borderColor: 'rgba(255,255,255,0.10)'
                        },
                        focus: {
                            borderColor: '#10b981',
                            shadow: '0 0 0 2px rgba(16,185,129,0.2)'
                        }
                    },
                    overlay: {
                        background: '#1a1a1d',
                        borderColor: 'rgba(255,255,255,0.10)',
                        shadow: '0 8px 30px rgba(0,0,0,0.5), 0 0 1px rgba(255,255,255,0.1)'
                    },
                    option: {
                        color: '#8b8b8e',
                        hover: {
                            background: 'rgba(255,255,255,0.05)',
                            color: '#ececef'
                        },
                        selected: {
                            background: 'rgba(16,185,129,0.15)',
                            color: '#34d399'
                        }
                    }
                }
            }
        },
        paginator: {
            colorScheme: {
                dark: {
                    root: {
                        background: 'transparent'
                    },
                    pageButton: {
                        background: 'transparent',
                        borderColor: 'transparent',
                        color: '#5c5c5f',
                        hover: {
                            background: 'rgba(255,255,255,0.05)',
                            color: '#ececef'
                        }
                    },
                    currentPage: {
                        background: 'rgba(16,185,129,0.15)',
                        borderColor: 'transparent',
                        color: '#34d399'
                    }
                }
            }
        }
    }
});

export default MyPreset;
