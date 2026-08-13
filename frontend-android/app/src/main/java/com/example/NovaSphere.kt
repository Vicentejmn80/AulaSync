package com.example

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavHostController
import com.example.ui.theme.CyanAccent
import com.example.ui.theme.PinkAccent
import com.example.ui.theme.PurpleAccent
import kotlinx.coroutines.delay
import kotlin.math.roundToInt

@Composable
fun NovaSphere(navController: NavHostController, viewModel: NovaViewModel) {
    val configuration = LocalConfiguration.current
    val density = LocalDensity.current
    
    val screenWidthPx = with(density) { configuration.screenWidthDp.dp.toPx() }
    val screenHeightPx = with(density) { configuration.screenHeightDp.dp.toPx() }
    
    val fabSize = 64.dp
    val fabSizePx = with(density) { fabSize.toPx() }
    
    var offsetX by remember { mutableFloatStateOf(screenWidthPx - fabSizePx - with(density) { 32.dp.toPx() }) }
    var offsetY by remember { mutableFloatStateOf(screenHeightPx - fabSizePx - with(density) { 150.dp.toPx() }) }
    
    var expanded by remember { mutableStateOf(false) }
    var showVoiceModal by remember { mutableStateOf(false) }
    var showOcrModal by remember { mutableStateOf(false) }
    var showEmergencyModal by remember { mutableStateOf(false) }

    val rotation by animateFloatAsState(
        targetValue = if (expanded) 45f else 0f,
        label = "rotation"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
    ) {
        if (expanded) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .clickable(
                        interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() },
                        indication = null
                    ) { expanded = false }
            )
        }

        Box(
            modifier = Modifier
                .offset { IntOffset(offsetX.roundToInt(), offsetY.roundToInt()) }
        ) {
            SpeedDialMenu(
                expanded = expanded,
                onVoiceClick = { showVoiceModal = true; expanded = false },
                onOcrClick = { showOcrModal = true; expanded = false },
                onChatClick = { navController.navigate(Screen.Chat.route); expanded = false },
                onEmergencyClick = { showEmergencyModal = true; expanded = false }
            )
            
            Box(
                modifier = Modifier
                    .size(fabSize)
                    .shadow(12.dp, CircleShape)
                    .clip(CircleShape)
                    .background(Brush.linearGradient(listOf(PurpleAccent, CyanAccent)))
                    .pointerInput(Unit) {
                        detectDragGestures { change, dragAmount ->
                            change.consume()
                            offsetX = (offsetX + dragAmount.x).coerceIn(0f, screenWidthPx - fabSizePx)
                            offsetY = (offsetY + dragAmount.y).coerceIn(0f, screenHeightPx - fabSizePx)
                            if (expanded) expanded = false
                        }
                    }
                    .clickable { expanded = !expanded },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.AutoAwesome,
                    contentDescription = "Aulasync AI",
                    tint = Color.White,
                    modifier = Modifier
                        .size(32.dp)
                        .graphicsLayer { rotationZ = rotation }
                )
            }
        }
        
        if (showVoiceModal) {
            VoiceRubricModal(onDismiss = { showVoiceModal = false })
        }
        if (showOcrModal) {
            OcrScannerModal(viewModel = viewModel, onDismiss = { showOcrModal = false })
        }
        if (showEmergencyModal) {
            EmergencyClassModal(onDismiss = { showEmergencyModal = false })
        }
    }
}

@Composable
fun SpeedDialMenu(
    expanded: Boolean,
    onVoiceClick: () -> Unit,
    onOcrClick: () -> Unit,
    onChatClick: () -> Unit,
    onEmergencyClick: () -> Unit
) {
    if (expanded) {
        Column(
            modifier = Modifier
                .offset(x = 8.dp, y = (-220).dp),
            horizontalAlignment = Alignment.End,
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            SpeedDialItem(icon = Icons.Default.Mic, label = "Dictar Tarea", expanded = expanded, delay = 150, onClick = onVoiceClick)
            SpeedDialItem(icon = Icons.Default.CameraAlt, label = "Escanear Plan", expanded = expanded, delay = 100, onClick = onOcrClick)
            SpeedDialItem(icon = Icons.Default.Edit, label = "Consulta Rápida", expanded = expanded, delay = 50, onClick = onChatClick)
            SpeedDialItem(icon = Icons.Default.Add, label = "Clase de Emergencia", expanded = expanded, delay = 0, onClick = onEmergencyClick)
        }
    }
}

@Composable
fun SpeedDialItem(icon: ImageVector, label: String, expanded: Boolean, delay: Int, onClick: () -> Unit) {
    AnimatedVisibility(
        visible = expanded,
        enter = scaleIn(animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow)),
        exit = scaleOut()
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.End
        ) {
            Box(
                modifier = Modifier
                    .background(Color(0xFF1E293B), RoundedCornerShape(8.dp))
                    .padding(horizontal = 8.dp, vertical = 4.dp)
            ) {
                Text(label, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Medium)
            }
            Spacer(modifier = Modifier.width(12.dp))
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .shadow(4.dp, CircleShape)
                    .clip(CircleShape)
                    .background(Color(0xFF1E293B))
                    .clickable(onClick = onClick),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = label, tint = CyanAccent, modifier = Modifier.size(24.dp))
            }
        }
    }
}

@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun VoiceRubricModal(onDismiss: () -> Unit) {
    var state by remember { mutableIntStateOf(0) }
    
    LaunchedEffect(Unit) {
        delay(2000)
        state = 1
        delay(1500)
        state = 2
    }
    
    androidx.compose.material3.ModalBottomSheet(onDismissRequest = onDismiss, containerColor = androidx.compose.material3.MaterialTheme.colorScheme.surface) {
        Column(modifier = Modifier.fillMaxWidth().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            when (state) {
                0 -> {
                    Icon(Icons.Default.Mic, contentDescription = null, tint = CyanAccent, modifier = Modifier.size(64.dp))
                    Spacer(modifier = Modifier.height(16.dp))
                    Text("Escuchando...", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface, fontSize = 20.sp)
                    Text("Ej: 'Crear rúbrica de exposición'", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurfaceVariant)
                }
                1 -> {
                    androidx.compose.material3.CircularProgressIndicator(color = PurpleAccent)
                    Spacer(modifier = Modifier.height(16.dp))
                    Text("Generando Rúbrica con Aulasync IA...", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
                }
                2 -> {
                    Text("Rúbrica: Exposición Oral", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
                    Spacer(modifier = Modifier.height(16.dp))
                    androidx.compose.material3.Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = androidx.compose.material3.CardDefaults.cardColors(containerColor = androidx.compose.material3.MaterialTheme.colorScheme.surfaceVariant)
                    ) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Text("1. Claridad de voz (30%)", fontWeight = FontWeight.Bold, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
                            Text("2. Dominio del tema (40%)", fontWeight = FontWeight.Bold, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
                            Text("3. Uso de recursos (30%)", fontWeight = FontWeight.Bold, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
                        }
                    }
                    Spacer(modifier = Modifier.height(24.dp))
                    androidx.compose.material3.Button(
                        onClick = onDismiss,
                        colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = PinkAccent),
                        modifier = Modifier.padding(bottom=32.dp)
                    ) {
                        Text("Guardar en Supabase")
                    }
                }
            }
        }
    }
}

@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun EmergencyClassModal(onDismiss: () -> Unit) {
    var step by remember { mutableIntStateOf(0) }
    var topic by remember { mutableStateOf("") }
    var duration by remember { mutableStateOf("15") }

    androidx.compose.material3.ModalBottomSheet(
        onDismissRequest = onDismiss, 
        containerColor = androidx.compose.material3.MaterialTheme.colorScheme.surface,
        modifier = Modifier.fillMaxHeight(0.85f)
    ) {
        Column(modifier = Modifier.fillMaxWidth().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = PinkAccent, modifier = Modifier.size(48.dp))
            Spacer(modifier = Modifier.height(16.dp))
            Text("Clase de Emergencia", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
            Spacer(modifier = Modifier.height(24.dp))
            
            if (step == 0) {
                androidx.compose.material3.OutlinedTextField(
                    value = topic,
                    onValueChange = { topic = it },
                    label = { Text("¿Qué tema debes enseñar hoy?") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp)
                )
                Spacer(modifier = Modifier.height(16.dp))
                androidx.compose.material3.OutlinedTextField(
                    value = duration,
                    onValueChange = { duration = it },
                    label = { Text("Duración (minutos)") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp)
                )
                Spacer(modifier = Modifier.height(32.dp))
                androidx.compose.material3.Button(
                    onClick = { step = 1 },
                    modifier = Modifier.fillMaxWidth().height(50.dp),
                    colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = PurpleAccent),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Text("Generar Plan con IA", color = Color.White)
                }
            } else if (step == 1) {
                LaunchedEffect(Unit) {
                    delay(2000)
                    step = 2
                }
                androidx.compose.material3.CircularProgressIndicator(color = CyanAccent)
                Spacer(modifier = Modifier.height(16.dp))
                Text("Buscando en el currículo...", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurfaceVariant)
            } else {
                androidx.compose.material3.Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = androidx.compose.material3.CardDefaults.cardColors(containerColor = androidx.compose.material3.MaterialTheme.colorScheme.surfaceVariant)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("💡 Inicio (5 min):", fontWeight = FontWeight.Bold, color = CyanAccent)
                        Text("Pregunta detonadora: ¿Por qué es importante '$topic'? Lluvia de ideas rápida.", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurfaceVariant)
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("📚 Desarrollo (${duration.toIntOrNull()?.minus(10) ?: 5} min):", fontWeight = FontWeight.Bold, color = PurpleAccent)
                        Text("Explicación ágil basada en 3 conceptos clave de este tema. Lectura compartida.", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurfaceVariant)
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("🎯 Cierre (5 min):", fontWeight = FontWeight.Bold, color = PinkAccent)
                        Text("Dinámica: Cada alumno escribe una palabra clave en su cuaderno.", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                Spacer(modifier = Modifier.height(24.dp))
                androidx.compose.material3.Button(
                    onClick = onDismiss,
                    modifier = Modifier.fillMaxWidth().height(50.dp),
                    colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = CyanAccent),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Text("¡Guardar y Usar!", color = Color.Black)
                }
            }
        }
    }
}
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun OcrScannerModal(viewModel: NovaViewModel, onDismiss: () -> Unit) {
    var isScanning by remember { mutableStateOf(true) }
    
    LaunchedEffect(Unit) {
        delay(3000)
        isScanning = false
    }
    
    androidx.compose.material3.ModalBottomSheet(
        onDismissRequest = onDismiss, 
        containerColor = androidx.compose.material3.MaterialTheme.colorScheme.surface,
        modifier = Modifier.fillMaxHeight(0.8f)
    ) {
        Column(modifier = Modifier.fillMaxSize().padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text("Escáner de Notas OCR", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
            Spacer(modifier = Modifier.height(24.dp))
            
            if (isScanning) {
                Box(
                    modifier = Modifier.fillMaxWidth().height(300.dp)
                        .border(2.dp, CyanAccent, RoundedCornerShape(16.dp))
                        .background(Color.Black.copy(alpha=0.5f))
                ) {
                    Box(modifier = Modifier.fillMaxWidth().height(4.dp).background(CyanAccent).align(Alignment.Center))
                    Text("Enfoca el documento de calificaciones...", modifier = Modifier.align(Alignment.BottomCenter).padding(16.dp), color = Color.White)
                }
                Spacer(modifier = Modifier.height(24.dp))
                androidx.compose.material3.CircularProgressIndicator(color = CyanAccent)
            } else {
                Icon(androidx.compose.material.icons.Icons.Default.CheckCircle, contentDescription = null, tint = Color(0xFF22C55E), modifier = Modifier.size(64.dp))
                Spacer(modifier = Modifier.height(16.dp))
                Text("¡Notas extraídas con éxito!", color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface, fontSize = 18.sp)
                Spacer(modifier = Modifier.height(16.dp))
                val students = viewModel.students.collectAsState().value.take(3)
                students.forEach { s ->
                    Row(modifier = Modifier.fillMaxWidth().padding(8.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(s.name, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurface)
                        Text((4..7).random().toString() + ".0", color = CyanAccent, fontWeight = FontWeight.Bold)
                    }
                }
                Spacer(modifier = Modifier.height(24.dp))
                androidx.compose.material3.Button(onClick = onDismiss, colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = CyanAccent)) {
                    Text("Validar y Cargar Notas", color=Color.Black)
                }
            }
        }
    }
}
